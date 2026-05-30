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
use App\Assistant\Domain\Model\Message;
use App\Assistant\Domain\Model\ValueObject\MessageRole;
use App\Assistant\Domain\Model\ValueObject\ModelName;
use App\Assistant\Domain\Model\ValueObject\SessionId;
use App\Assistant\Domain\Port\SessionRepository;
use Revolt\EventLoop;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Tui\Event\SubmitEvent;
use Symfony\Component\Tui\Style\Padding;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\TextWidget;

#[AsCommand(
    name: 'assistant:tui',
    description: 'Interactive TUI chat with the assistant (symfony/tui).',
)]
final class TuiCommand extends Command
{
    private const string MARKER_USER = '▶ You';
    private const string MARKER_ASSISTANT = '◀ Assistant';
    private const string MARKER_THINKING = '◀ Assistant — (thinking…)';
    private const string MARKER_ERROR = '⚠ Error';

    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly StartSessionHandler $startSession,
        private readonly SendMessageHandler $sendMessage,
        private readonly GetSessionMessagesHandler $getMessages,
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
            $sessionId = $this->resolveSession($input, $io);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $session = $this->sessions->findById($sessionId);
        if (null === $session) {
            $io->error(\sprintf('Session "%s" not found.', $sessionId->value));

            return Command::FAILURE;
        }

        $tui = $this->buildTui($sessionId, $session->model->value, $session->title());

        // Hydrate the transcript with the existing history (if resumed session).
        $transcript = $this->widget($tui, 'transcript');
        \assert($transcript instanceof ContainerWidget);
        foreach (($this->getMessages)(new GetSessionMessagesQuery($sessionId)) as $msg) {
            $this->appendMessageWidget($transcript, $msg);
        }

        $tui->run();

        return Command::SUCCESS;
    }

    private function resolveSession(InputInterface $input, SymfonyStyle $io): SessionId
    {
        $existing = $input->getOption('session');
        if (\is_string($existing) && '' !== $existing) {
            return SessionId::fromString($existing);
        }

        $model = (string) $input->getOption('model');
        $title = (string) $input->getOption('title');
        $sessionId = ($this->startSession)(new StartSessionCommand(ModelName::of($model), $title));
        $io->writeln(\sprintf('<info>Started new session %s (model=%s)</info>', $sessionId->value, $model));

        return $sessionId;
    }

    private function buildTui(SessionId $sessionId, string $model, string $title): Tui
    {
        $styles = new StyleSheet([
            ':root' => new Style(background: '#0b1220', color: '#e5e7eb'),
            '.header' => new Style(padding: Padding::xy(1, 0), background: '#1f2937', color: '#a5b4fc'),
            '.transcript' => new Style(padding: Padding::xy(1, 1)),
            '.user' => new Style(color: '#60a5fa'),
            '.assistant' => new Style(color: '#34d399'),
            '.thinking' => new Style(color: '#fbbf24'),
            '.error' => new Style(color: '#f87171'),
            '.input' => new Style(padding: Padding::xy(1, 0), background: '#1f2937', color: '#e5e7eb'),
        ]);

        $tui = new Tui($styles);

        $header = (new TextWidget(\sprintf('SebCode TUI — %s · model=%s · %s', $title, $model, $sessionId->value)))
            ->setId('header')
            ->addStyleClass('header');

        $transcript = (new ContainerWidget())
            ->setId('transcript')
            ->expandVertically(true)
            ->addStyleClass('transcript');

        $inputWidget = (new InputWidget())
            ->setId('input')
            ->setPrompt('› ')
            ->addStyleClass('input');

        $tui->add($header);
        $tui->add($transcript);
        $tui->add($inputWidget);
        $tui->setFocus($inputWidget);

        $inputWidget->onSubmit(function (SubmitEvent $event) use ($tui, $transcript, $inputWidget, $sessionId): void {
            $text = trim($inputWidget->getValue());
            if ('' === $text) {
                return;
            }

            if (\in_array(strtolower($text), [':q', ':quit', 'exit', 'quit'], true)) {
                $tui->stop();

                return;
            }

            $inputWidget->setValue('');

            $this->appendLine($transcript, self::MARKER_USER, $text, 'user');
            $thinking = $this->appendLine($transcript, self::MARKER_THINKING, '', 'thinking');
            $tui->requestRender();

            EventLoop::queue(function () use ($transcript, $thinking, $sessionId, $text, $tui): void {
                try {
                    $result = ($this->sendMessage)(new SendMessageCommand($sessionId, $text));
                    $transcript->remove($thinking);
                    $this->appendLine(
                        $transcript,
                        self::MARKER_ASSISTANT,
                        $result->assistantMessage->content->text,
                        'assistant',
                    );
                } catch (LlmUnavailable $e) {
                    $transcript->remove($thinking);
                    $this->appendLine(
                        $transcript,
                        self::MARKER_ERROR,
                        \sprintf('LLM unavailable: %s', $e->getMessage()),
                        'error',
                    );
                } catch (\Throwable $e) {
                    $transcript->remove($thinking);
                    $this->appendLine(
                        $transcript,
                        self::MARKER_ERROR,
                        $e->getMessage(),
                        'error',
                    );
                }
                $tui->requestRender();
            });
        });

        return $tui;
    }

    private function widget(Tui $tui, string $id): mixed
    {
        return $tui->getById($id);
    }

    private function appendMessageWidget(ContainerWidget $transcript, Message $message): void
    {
        [$marker, $class] = match ($message->role) {
            MessageRole::User => [self::MARKER_USER, 'user'],
            MessageRole::Assistant => [self::MARKER_ASSISTANT, 'assistant'],
            MessageRole::System => ['⚙ System', 'thinking'],
            MessageRole::Tool => ['🔧 Tool', 'thinking'],
        };
        $this->appendLine($transcript, $marker, $message->content->text, $class);
    }

    private function appendLine(
        ContainerWidget $transcript,
        string $marker,
        string $text,
        string $cssClass,
    ): TextWidget {
        $body = '' === $text ? $marker : $marker."\n".$text."\n";
        $widget = (new TextWidget($body))->addStyleClass($cssClass);
        $transcript->add($widget);

        return $widget;
    }
}
