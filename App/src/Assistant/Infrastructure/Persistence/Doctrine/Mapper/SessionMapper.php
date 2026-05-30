<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Persistence\Doctrine\Mapper;

use App\Assistant\Domain\Model\Session;
use App\Assistant\Domain\Model\ValueObject\ModelName;
use App\Assistant\Domain\Model\ValueObject\SessionId;
use App\Assistant\Infrastructure\Persistence\Doctrine\Entity\SessionEntity;

final class SessionMapper
{
    public function toEntity(Session $session, ?SessionEntity $existing = null): SessionEntity
    {
        $entity = $existing ?? new SessionEntity();
        $entity->id = $session->id->value;
        $entity->model = $session->model->value;
        $entity->title = $session->title();
        $entity->createdAt = $session->createdAt;
        $entity->updatedAt = $session->updatedAt();
        $entity->archived = $session->isArchived();

        return $entity;
    }

    public function toAggregate(SessionEntity $entity): Session
    {
        return new Session(
            SessionId::fromString($entity->id),
            ModelName::of($entity->model),
            $entity->title,
            $entity->createdAt,
            $entity->updatedAt,
            $entity->archived,
        );
    }
}
