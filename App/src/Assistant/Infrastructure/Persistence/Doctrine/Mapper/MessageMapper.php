<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Persistence\Doctrine\Mapper;

use App\Assistant\Domain\Model\Message;
use App\Assistant\Domain\Model\ValueObject\MessageContent;
use App\Assistant\Domain\Model\ValueObject\MessageId;
use App\Assistant\Domain\Model\ValueObject\MessageRole;
use App\Assistant\Domain\Model\ValueObject\SessionId;
use App\Assistant\Infrastructure\Persistence\Doctrine\Entity\MessageEntity;

final class MessageMapper
{
    public function toEntity(Message $message): MessageEntity
    {
        $entity = new MessageEntity();
        $entity->id = $message->id->value;
        $entity->sessionId = $message->sessionId->value;
        $entity->role = $message->role->value;
        $entity->content = $message->content->text;
        $entity->createdAt = $message->createdAt;

        return $entity;
    }

    public function toAggregate(MessageEntity $entity): Message
    {
        return new Message(
            MessageId::fromString($entity->id),
            SessionId::fromString($entity->sessionId),
            MessageRole::from($entity->role),
            MessageContent::of($entity->content),
            $entity->createdAt,
        );
    }
}
