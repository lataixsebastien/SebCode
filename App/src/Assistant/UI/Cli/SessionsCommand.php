<?php

declare(strict_types=1);

namespace App\Assistant\UI\Cli;

use App\Assistant\Application\Query\GetSessionMessagesHandler;
use App\Assistant\Application\Query\GetSessionMessagesQuery;
use App\Assistant\Domain\Model\ValueObject\SessionId;
use App\Assistant\Domain\Port\SessionRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'assistant:sessions',
    description: 'List persisted assistant sessions, or show the messages of one.',
)]
final class SessionsCommand extends Command
{
    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly GetSessionMessagesHandler $getMessages,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'session',
            InputArgument::OPTIONAL,
            'Session id (ses_…) to show messages for. Omit to list all sessions.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sessionArg = $input->getArgument('session');

        if (null === $sessionArg) {
            return $this->listSessions($io);
        }

        return $this->showSession($io, (string) $sessionArg);
    }

    private function listSessions(SymfonyStyle $io): int
    {
        $sessions = $this->sessions->all();
        if ([] === $sessions) {
            $io->warning('No sessions yet. Try: bin/console assistant:ask "Hello"');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($sessions as $s) {
            $rows[] = [
                $s->id->value,
                $s->title(),
                $s->model->value,
                $s->updatedAt()->format('Y-m-d H:i:s'),
                $s->isArchived() ? 'yes' : 'no',
            ];
        }

        $io->table(['id', 'title', 'model', 'updated_at', 'archived'], $rows);

        return Command::SUCCESS;
    }

    private function showSession(SymfonyStyle $io, string $rawId): int
    {
        try {
            $sessionId = SessionId::fromString($rawId);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $session = $this->sessions->findById($sessionId);
        if (null === $session) {
            $io->error(\sprintf('Session "%s" not found.', $sessionId->value));

            return Command::FAILURE;
        }

        $io->title(\sprintf('Session %s — %s', $session->id->value, $session->title()));
        $io->writeln(\sprintf(
            '<comment>model=%s  created=%s  updated=%s  archived=%s</comment>',
            $session->model->value,
            $session->createdAt->format('Y-m-d H:i:s'),
            $session->updatedAt()->format('Y-m-d H:i:s'),
            $session->isArchived() ? 'yes' : 'no',
        ));
        $io->newLine();

        $messages = ($this->getMessages)(new GetSessionMessagesQuery($sessionId));
        if ([] === $messages) {
            $io->warning('Session has no messages yet.');

            return Command::SUCCESS;
        }

        foreach ($messages as $msg) {
            $io->section(ucfirst($msg->role->value).' — '.$msg->createdAt->format('H:i:s'));
            $io->writeln($msg->content->text);
        }

        return Command::SUCCESS;
    }
}
