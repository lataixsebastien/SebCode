<?php

declare(strict_types=1);

namespace App\Assistant\UI\Tui;

use App\Assistant\Application\Command\SendMessageCommand;
use App\Assistant\Application\Command\SendMessageHandler;
use App\Assistant\Application\Command\StartSessionCommand;
use App\Assistant\Application\Command\StartSessionHandler;
use App\Assistant\Application\Query\GetSessionMessagesHandler;
use App\Assistant\Application\Query\GetSessionMessagesQuery;
use App\Assistant\Domain\Exception\LlmUnavailable;
use App\Assistant\Domain\Model\Session;
use App\Assistant\Domain\Model\ValueObject\ModelName;
use App\Assistant\Domain\Model\ValueObject\SessionId;
use App\Assistant\Domain\Port\AgentOutputStreamRegistry;
use App\Assistant\Domain\Port\ModelCatalog;
use App\Assistant\Domain\Port\SessionRepository;
use App\Assistant\UI\Tui\Component\PromptHistory;
use App\Assistant\UI\Tui\Component\SlashCommands;
use App\Assistant\UI\Tui\Component\StatusBarWidget;
use App\Assistant\UI\Tui\Component\TranscriptView;
use App\Tool\Domain\Port\PermissionConsoleRegistry;
use Revolt\EventLoop;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Tui\Event\CancelEvent;
use Symfony\Component\Tui\Event\ChangeEvent;
use Symfony\Component\Tui\Event\SelectEvent;
use Symfony\Component\Tui\Event\SubmitEvent;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * Interactive TUI chat, mirroring the opencode terminal UI:
 *
 * - immutable scrollback transcript (markdown assistant replies, per-tool
 *   formatted calls/results, accent-barred user prompts, splash banner);
 * - footer with a rounded-border composer, a slash-command palette, model
 *   and session pickers, and a two-row status bar (agent · model · session,
 *   spinner/elapsed/hints, token usage);
 * - prompt history (↑/↓), Esc to clear/close/interrupt, double Ctrl+C to
 *   quit, PgUp/PgDn to scroll the transcript.
 */
#[AsCommand(
    name: 'assistant:tui',
    description: 'Interactive TUI chat with the assistant (opencode-style).',
)]
final class TuiCommand extends Command
{
    private const string AGENT_NAME = 'build';
    private const int SCROLL_STEP = 10;

    /** Footer mode: who owns navigation keys. */
    private string $mode = 'composer';

    private ?Tui $tui = null;
    private TranscriptView $view;
    private StatusBarWidget $statusBar;
    private ContainerWidget $palettePanel;
    private TextWidget $paletteTitle;
    private SelectListWidget $paletteList;
    private InputWidget $input;
    private TuiAgentOutputStream $stream;
    private PromptHistory $history;
    private Keybindings $keys;

    private SessionId $sessionId;
    private ModelName $model;
    private bool $exitArmed = false;
    private int $scrollOffset = 0;

    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly StartSessionHandler $startSession,
        private readonly SendMessageHandler $sendMessage,
        private readonly GetSessionMessagesHandler $getMessages,
        private readonly ModelCatalog $models,
        private readonly PermissionConsoleRegistry $consoles,
        private readonly AgentOutputStreamRegistry $streams,
        private readonly string $defaultModel,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('session', 's', InputOption::VALUE_REQUIRED, 'Resume an existing session id (ses_…).')
            ->addOption('model', 'm', InputOption::VALUE_REQUIRED, 'Model for a new session.', $this->defaultModel)
            ->addOption('title', 't', InputOption::VALUE_REQUIRED, 'Title for a new session.', 'TUI chat');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $sessionId = $this->resolveSession($input);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $session = $this->sessions->findById($sessionId);
        if (null === $session) {
            $io->error(\sprintf('Session "%s" not found.', $sessionId->value));

            return Command::FAILURE;
        }

        $this->sessionId = $session->id;
        $this->model = $session->model;
        $this->history = new PromptHistory();
        $this->keys = new Keybindings([
            'quit' => ['ctrl+c'],
            'up' => [Key::UP, 'ctrl+p'],
            'down' => [Key::DOWN, 'ctrl+n'],
            'enter' => [Key::ENTER],
            'esc' => [Key::ESCAPE],
            'page_up' => [Key::PAGE_UP],
            'page_down' => [Key::PAGE_DOWN],
        ]);

        $tui = $this->buildTui();
        $this->showSession($session, hydrate: true);

        // Let mutating tools (write/edit/bash) prompt this terminal for
        // permission from deep in the (blocked) agent loop. Detached in finally
        // so the shared registry never leaks a stale console.
        $dialogSlot = $tui->getById('dialog-slot');
        \assert($dialogSlot instanceof ContainerWidget);
        $console = new TuiPermissionConsole($tui, $dialogSlot, $this->view);
        $console->activate();
        $this->consoles->attach($console);

        // Stream the assistant's text + tool calls/results live into the transcript.
        $this->stream = new TuiAgentOutputStream($tui, $this->view, $this->statusBar);
        $this->streams->attach($this->stream);

        try {
            $tui->run();
        } finally {
            $this->consoles->detach();
            $console->deactivate();
            $this->streams->detach();
        }

        return Command::SUCCESS;
    }

    private function resolveSession(InputInterface $input): SessionId
    {
        $existing = $input->getOption('session');
        if (\is_string($existing) && '' !== $existing) {
            return SessionId::fromString($existing);
        }

        $model = $input->getOption('model');
        $title = $input->getOption('title');
        \assert(\is_string($model) && \is_string($title));

        return ($this->startSession)(new StartSessionCommand(ModelName::of($model), $title));
    }

    // ── Layout ──────────────────────────────────────────────────────────

    private function buildTui(): Tui
    {
        $tui = new Tui(TuiTheme::styleSheet());
        $this->tui = $tui;

        $transcript = (new ContainerWidget())
            ->setId('transcript')
            ->expandVertically(true)
            ->addStyleClass('transcript');
        $this->view = new TranscriptView($transcript);

        // Footer: palette (hidden by default) + permission dialog slot +
        // bordered composer + two-row status bar.
        $this->paletteTitle = (new TextWidget('Commands'))->addStyleClass('palette-title');
        $this->paletteList = new SelectListWidget(SlashCommands::items(), maxVisible: 6);
        $this->palettePanel = (new ContainerWidget())->addStyleClass('palette');
        $this->palettePanel->add($this->paletteTitle);
        $this->palettePanel->add($this->paletteList);
        $this->palettePanel->setStyle(new Style(hidden: true));

        $dialogSlot = (new ContainerWidget())->setId('dialog-slot');

        $this->input = (new InputWidget())
            ->setId('input')
            ->setPrompt(Color::from(TuiTheme::PRIMARY)->toForegroundCode().'> '."\x1b[39m");
        $composer = (new ContainerWidget())->addStyleClass('composer');
        $composer->add($this->input);

        $this->statusBar = new StatusBarWidget();

        $footer = new ContainerWidget();
        $footer->add($this->palettePanel);
        $footer->add($dialogSlot);
        $footer->add($composer);
        $footer->add($this->statusBar);

        $tui->add($transcript);
        $tui->add($footer);
        $tui->setFocus($this->input);

        $this->wireInput();
        $this->wirePaletteList();

        return $tui;
    }

    // ── Input wiring ────────────────────────────────────────────────────

    private function wireInput(): void
    {
        // Raw-key hook, checked before the InputWidget's own bindings.
        $this->input->onInput(fn (string $data): bool => $this->handleComposerKey($data));

        // Typing `/…` opens and filters the command palette.
        $this->input->onChange(function (ChangeEvent $event): void {
            $value = $event->getValue();
            if (str_starts_with($value, '/')) {
                $this->openPalette($value);
            } elseif ('palette' === $this->mode) {
                $this->closePalette();
            }
        });

        $this->input->onSubmit(function (SubmitEvent $event): void {
            $text = trim($event->getValue());
            if ('' === $text || $this->statusBar->isWorking()) {
                return;
            }

            $normalized = SlashCommands::normalize($text);
            if (SlashCommands::isCommand($normalized)) {
                $this->input->setValue('');
                $this->executeCommand($normalized);

                return;
            }

            $this->submitPrompt($text);
        });
    }

    private function handleComposerKey(string $data): bool
    {
        if ($this->keys->matches($data, 'quit')) {
            $this->handleCtrlC();

            return true;
        }

        if ($this->exitArmed) {
            $this->exitArmed = false;
            $this->statusBar->armExit(false);
        }

        if ($this->keys->matches($data, 'page_up')) {
            $this->scrollBy(self::SCROLL_STEP);

            return true;
        }

        if ($this->keys->matches($data, 'page_down')) {
            $this->scrollBy(-self::SCROLL_STEP);

            return true;
        }

        if ('palette' === $this->mode) {
            return $this->handlePaletteKey($data);
        }

        if ($this->keys->matches($data, 'up')) {
            $recalled = $this->history->previous($this->input->getValue());
            if (null !== $recalled) {
                $this->input->setValue($recalled);
            }

            return true;
        }

        if ($this->keys->matches($data, 'down')) {
            $recalled = $this->history->next();
            if (null !== $recalled) {
                $this->input->setValue($recalled);
            }

            return true;
        }

        if ($this->keys->matches($data, 'esc')) {
            $this->input->setValue('');
            $this->history->resetCursor();

            return true;
        }

        return false;
    }

    private function handlePaletteKey(string $data): bool
    {
        if ($this->keys->matches($data, 'up') || $this->keys->matches($data, 'down')) {
            $this->paletteList->handleInput($data);

            return true;
        }

        if ($this->keys->matches($data, 'enter')) {
            $item = $this->paletteList->getSelectedItem();
            $this->closePalette();
            $this->input->setValue('');
            if (null !== $item) {
                $this->executeCommand($item['value']);
            }

            return true;
        }

        if ($this->keys->matches($data, 'esc')) {
            $this->closePalette();
            $this->input->setValue('');

            return true;
        }

        return false;
    }

    private function handleCtrlC(): void
    {
        // First Ctrl+C clears a non-empty draft (opencode behaviour), the
        // next one arms the exit warning, and a press while armed quits.
        if ($this->exitArmed) {
            $this->tui?->stop();

            return;
        }

        if ('' !== $this->input->getValue()) {
            $this->input->setValue('');
            if ('palette' === $this->mode) {
                $this->closePalette();
            }

            return;
        }

        $this->exitArmed = true;
        $this->statusBar->armExit(true);
    }

    // ── Palette & pickers ───────────────────────────────────────────────

    private function wirePaletteList(): void
    {
        $this->paletteList->onSelect(function (SelectEvent $event): void {
            // Fired in picker modes only (the list has focus there); the
            // palette mode resolves Enter itself in handlePaletteKey().
            $value = $event->getValue();
            $mode = $this->mode;
            $this->closePalette();

            if ('models' === $mode) {
                $this->startNewSession(ModelName::of($value));
            } elseif ('sessions' === $mode) {
                $this->switchToSession($value);
            }
        });

        $this->paletteList->onCancel(function (CancelEvent $event): void {
            if ('composer' !== $this->mode) {
                $this->closePalette();
            }
        });
    }

    private function openPalette(string $filter): void
    {
        $this->paletteTitle->setText('Commands');
        if ('palette' !== $this->mode) {
            $this->paletteList->setItems(SlashCommands::items());
            $this->palettePanel->setStyle(null);
            $this->mode = 'palette';
        }
        $this->paletteList->setFilter($filter);
        $this->tui?->requestRender();
    }

    /**
     * @param list<array{value: string, label: string, description?: string}> $items
     */
    private function openPicker(string $mode, string $title, array $items): void
    {
        $this->paletteTitle->setText($title);
        $this->paletteList->setItems($items);
        $this->palettePanel->setStyle(null);
        $this->mode = $mode;
        $this->tui?->setFocus($this->paletteList);
        $this->tui?->requestRender();
    }

    private function closePalette(): void
    {
        $this->palettePanel->setStyle(new Style(hidden: true));
        $this->mode = 'composer';
        $this->tui?->setFocus($this->input);
        $this->tui?->requestRender();
    }

    // ── Slash commands ──────────────────────────────────────────────────

    private function executeCommand(string $command): void
    {
        match ($command) {
            SlashCommands::EXIT => $this->tui?->stop(),
            SlashCommands::CLEAR => $this->clearTranscript(),
            SlashCommands::HELP => $this->showHelp(),
            SlashCommands::NEW => $this->startNewSession($this->model),
            SlashCommands::MODELS => $this->openModelPicker(),
            SlashCommands::SESSIONS => $this->openSessionPicker(),
            default => null,
        };
    }

    private function clearTranscript(): void
    {
        $this->view->clear();
        $this->resetScroll();
        $this->tui?->requestRender(true);
    }

    private function showHelp(): void
    {
        $this->view->help();
        $this->resetScroll();
        $this->tui?->requestRender();
    }

    private function openModelPicker(): void
    {
        $models = $this->models->availableModels();
        if ([] === $models) {
            $this->view->error('No models found — is Ollama running?');
            $this->tui?->requestRender();

            return;
        }

        $items = array_map(
            fn (ModelName $model): array => [
                'value' => $model->value,
                'label' => $model->value,
                'description' => $model->value === $this->model->value ? 'current' : '',
            ],
            $models,
        );
        $this->openPicker('models', 'Select model (starts a new session)', $items);
    }

    private function openSessionPicker(): void
    {
        $sessions = $this->sessions->all();
        usort($sessions, static fn (Session $a, Session $b): int => $b->updatedAt() <=> $a->updatedAt());

        $items = array_map(
            fn (Session $session): array => [
                'value' => $session->id->value,
                'label' => $session->title(),
                'description' => \sprintf(
                    '%s · %s%s',
                    $session->id->value,
                    $session->model->value,
                    $session->id->value === $this->sessionId->value ? ' · current' : '',
                ),
            ],
            $sessions,
        );
        $this->openPicker('sessions', 'Select session', $items);
    }

    // ── Session lifecycle ───────────────────────────────────────────────

    private function startNewSession(ModelName $model): void
    {
        $sessionId = ($this->startSession)(new StartSessionCommand($model, 'TUI chat'));
        $session = $this->sessions->findById($sessionId);
        if (null === $session) {
            $this->view->error(\sprintf('Session "%s" not found after creation.', $sessionId->value));
            $this->tui?->requestRender();

            return;
        }

        $this->showSession($session, hydrate: false);
        $this->tui?->requestRender(true);
    }

    private function switchToSession(string $sessionId): void
    {
        $session = $this->sessions->findById(SessionId::fromString($sessionId));
        if (null === $session) {
            $this->view->error(\sprintf('Session "%s" not found.', $sessionId));
            $this->tui?->requestRender();

            return;
        }

        $this->showSession($session, hydrate: true);
        $this->tui?->requestRender(true);
    }

    private function showSession(Session $session, bool $hydrate): void
    {
        $this->sessionId = $session->id;
        $this->model = $session->model;
        $this->statusBar->setIdentity(self::AGENT_NAME, $session->model->value, $session->id->value);

        $this->view->clear();
        $this->view->splash($session->model->value, $session->id->value, $session->title());
        $this->resetScroll();

        if ($hydrate) {
            foreach (($this->getMessages)(new GetSessionMessagesQuery($session->id)) as $message) {
                $this->view->message($message);
            }
        }
    }

    // ── Prompt submission ───────────────────────────────────────────────

    private function submitPrompt(string $text): void
    {
        $this->history->push($text);
        $this->input->setValue('');
        $this->view->user($text);
        $this->resetScroll();
        $this->statusBar->startWorking();
        $this->stream->beginTurn();
        $this->tui?->requestRender();

        $sessionId = $this->sessionId;
        EventLoop::queue(function () use ($sessionId, $text): void {
            // Paint the user entry + working status before the blocking call.
            $this->tui?->requestRender(true);
            $this->tui?->processRender();

            try {
                $result = ($this->sendMessage)(new SendMessageCommand($sessionId, $text));
                $this->statusBar->addUsage($result->promptTokens, $result->completionTokens);
                $this->statusBar->setNotice($result->interrupted ? 'interrupted' : null);
            } catch (LlmUnavailable $e) {
                $this->view->error('LLM unavailable: '.$e->getMessage());
            } catch (\Throwable $e) {
                $this->view->error($e->getMessage());
            } finally {
                $this->statusBar->stopWorking();
                $this->tui?->requestRender(true);
            }
        });
    }

    // ── Transcript scrolling ────────────────────────────────────────────

    private function scrollBy(int $lines): void
    {
        $this->scrollOffset = max(0, $this->scrollOffset + $lines);
        $this->tui?->setScrollOffset($this->scrollOffset);
        $this->tui?->requestRender(true);
    }

    private function resetScroll(): void
    {
        if (0 !== $this->scrollOffset) {
            $this->scrollOffset = 0;
            $this->tui?->setScrollOffset(0);
        }
    }
}
