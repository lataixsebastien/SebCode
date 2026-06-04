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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'assistant:ask',
    description: 'Ask the assistant a one-shot question (creates a session if none given).',
)]
final class AskCommand extends Command
{
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
            ->addArgument(
                'question',
                InputArgument::REQUIRED,
                'The question to ask the assistant.',
            )
            ->addOption(
                'session',
                's',
                InputOption::VALUE_REQUIRED,
                'Continue an existing session by id (ses_…). Creates a new one if omitted.',
            )
            ->addOption(
                'model',
                'm',
                InputOption::VALUE_REQUIRED,
                'Override the LLM model for a new session (e.g. "qwen2.5:7b").',
                $this->defaultModel,
            )
            ->addOption(
                'title',
                't',
                InputOption::VALUE_REQUIRED,
                'Title for a newly-created session.',
                'CLI ask',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $question = $input->getArgument('question');
        \assert(\is_string($question));

        try {
            $sessionId = $this->resolveSessionId($input, $io);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $io->section('You');
        $io->writeln($question);
        $io->section('Assistant');

        // Let mutating tools prompt this terminal for permission, and stream the
        // assistant's progress (text + tool calls/results) live. Both detached
        // in finally so the shared registries never leak to the next run.
        $this->consoles->attach(new ConsolePermissionConsole($io, $input->isInteractive()));
        $this->streams->attach(new CliAgentOutputStream($output));

        try {
            $result = ($this->sendMessage)(new SendMessageCommand($sessionId, $question));
        } catch (SessionNotFound $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (LlmUnavailable $e) {
            $io->error(\sprintf('LLM unavailable: %s', $e->getMessage()));
            $io->note('Your message was persisted — re-run with --session='.$sessionId->value.' to retry.');

            return Command::FAILURE;
        } catch (AgentLoopExceeded $e) {
            $io->error($e->getMessage());
            $io->note('Inspect what happened: bin/console assistant:sessions '.$sessionId->value);

            return Command::FAILURE;
        } finally {
            $this->consoles->detach();
            $this->streams->detach();
        }

        $io->newLine(2);
        $io->writeln(\sprintf(
            '<comment>session=%s</comment>  <comment>prompt_tokens=%s</comment>  <comment>completion_tokens=%s</comment>',
            $sessionId->value,
            $result->promptTokens ?? '?',
            $result->completionTokens ?? '?',
        ));

        return Command::SUCCESS;
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
