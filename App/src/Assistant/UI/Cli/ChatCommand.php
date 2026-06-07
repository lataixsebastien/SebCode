<?php

declare(strict_types=1);

namespace App\Assistant\UI\Cli;

use App\Assistant\Application\Command\SendMessageCommand;
use App\Assistant\Application\Command\SendMessageHandler;
use App\Assistant\Application\Command\StartSessionCommand;
use App\Assistant\Application\Command\StartSessionHandler;
use App\Assistant\Domain\Exception\AgentLoopExceeded;
use App\Assistant\Domain\Exception\LlmUnavailable;
use App\Assistant\Domain\Exception\SessionNotFound;
use App\Assistant\Domain\Model\ValueObject\ModelName;
use App\Assistant\Domain\Model\ValueObject\SessionId;
use App\Assistant\Domain\Port\AgentOutputStreamRegistry;
use App\Tool\Domain\Port\PermissionConsoleRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Interactive console chat — a line-based REPL (cooked terminal mode, no raw
 * keystroke handling). Unlike {@see \App\Assistant\UI\Tui\TuiCommand}, it works
 * in *any* terminal, including `docker compose exec -it` from Windows PowerShell,
 * because it reads whole lines from STDIN instead of switching the terminal to
 * raw mode. It reuses the same session, streaming and permission plumbing as
 * {@see AskCommand}; the only addition is the read-eval loop.
 */
#[AsCommand(
    name: 'assistant:chat',
    description: 'Interactive console chat with the assistant (line REPL, works in any terminal).',
)]
final class ChatCommand extends Command
{
    /** @var list<string> */
    private const array EXIT_WORDS = ['/exit', '/quit', ':q', 'exit', 'quit'];

    public function __construct(
        private readonly StartSessionHandler $startSession,
        private readonly SendMessageHandler $sendMessage,
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
            ->addOption('title', 't', InputOption::VALUE_REQUIRED, 'Title for a new session.', 'Console chat');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $sessionId = $this->resolveSessionId($input, $io);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        // A real TTY is required to poll keystrokes mid-stream; a piped/one-shot
        // run (isInteractive() can still be true there) would otherwise see its
        // buffered input or EOF and abort instantly.
        $tty = (bool) @stream_isatty(\STDIN);

        $model = $input->getOption('model');
        \assert(\is_string($model));

        $io->writeln(\sprintf('<fg=cyan>╭─ SebCode · %s ─╮</>', $model));
        $hint = 'type your message · Enter to send · /exit to quit';
        if ($tty) {
            $hint .= ' · Enter mid-answer to interrupt';
        }
        $io->writeln('<fg=gray>  '.$hint.'</>');

        // Let mutating tools prompt this terminal for permission, and stream the
        // assistant's progress (text + tool calls/results) live. Detached in
        // finally so the shared registries never leak to the next command.
        $this->consoles->attach(new ConsolePermissionConsole($io, $input->isInteractive()));
        $sink = new CliAgentOutputStream($output, $tty);
        $this->streams->attach($sink);

        try {
            while (true) {
                $output->write("\n<fg=cyan>● You</> <fg=gray>›</> ");
                $line = fgets(\STDIN);
                if (false === $line) {
                    break; // EOF: Ctrl+D (Unix) / Ctrl+Z then Enter (Windows).
                }

                $text = trim($line);
                if ('' === $text) {
                    continue;
                }
                if (\in_array(strtolower($text), self::EXIT_WORDS, true)) {
                    break;
                }

                if (!$this->turn($io, $sink, $sessionId, $text)) {
                    break;
                }
            }
        } finally {
            $this->consoles->detach();
            $this->streams->detach();
        }

        $io->writeln(\sprintf("\n<fg=gray>Bye — resume with: assistant:chat --session=%s</>", $sessionId->value));

        return Command::SUCCESS;
    }

    /**
     * Run one user turn through the agent loop. Recoverable errors are reported
     * and the REPL keeps going (returns true); a vanished session is fatal and
     * ends the loop (returns false).
     */
    private function turn(SymfonyStyle $io, CliAgentOutputStream $sink, SessionId $sessionId, string $text): bool
    {
        $sink->startTurn();
        try {
            $result = ($this->sendMessage)(new SendMessageCommand($sessionId, $text));
        } catch (LlmUnavailable $e) {
            $io->error(\sprintf('LLM unavailable: %s', $e->getMessage()));

            return true;
        } catch (AgentLoopExceeded $e) {
            $io->error($e->getMessage());

            return true;
        } catch (SessionNotFound $e) {
            $io->error($e->getMessage());

            return false;
        } finally {
            $sink->endTurn();
        }

        $io->newLine();
        if ($result->interrupted) {
            $io->writeln('<fg=yellow>⏹ interrupted</>');
        }
        $io->writeln(\sprintf(
            '<fg=gray>⏱ %s tok in · %s tok out</>',
            $result->promptTokens ?? '?',
            $result->completionTokens ?? '?',
        ));

        return true;
    }

    private function resolveSessionId(InputInterface $input, SymfonyStyle $io): SessionId
    {
        $existing = $input->getOption('session');
        if (\is_string($existing) && '' !== $existing) {
            return SessionId::fromString($existing);
        }

        $model = $input->getOption('model');
        $title = $input->getOption('title');
        \assert(\is_string($model) && \is_string($title));

        $sessionId = ($this->startSession)(new StartSessionCommand(ModelName::of($model), $title));
        $io->writeln(\sprintf('<info>Started new session %s (model=%s)</info>', $sessionId->value, $model));

        return $sessionId;
    }
}
