<?php

declare(strict_types=1);

namespace App\Assistant\Application\Command;

use App\Assistant\Domain\Model\Session;
use App\Assistant\Domain\Model\ValueObject\SessionId;
use App\Assistant\Domain\Port\Clock;
use App\Assistant\Domain\Port\IdGenerator;
use App\Assistant\Domain\Port\SessionRepository;

final readonly class StartSessionHandler
{
    public function __construct(
        private SessionRepository $sessions,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    public function __invoke(StartSessionCommand $command): SessionId
    {
        $session = Session::start(
            $this->ids->nextSessionId(),
            $command->model,
            $command->title,
            $this->clock->now(),
        );

        $this->sessions->save($session);

        return $session->id;
    }
}
