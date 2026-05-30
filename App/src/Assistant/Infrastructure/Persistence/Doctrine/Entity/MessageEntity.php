<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'assistant_messages')]
#[ORM\Index(name: 'idx_msg_session_created', columns: ['session_id', 'created_at'])]
class MessageEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 64)]
    public string $id;

    #[ORM\Column(name: 'session_id', type: 'string', length: 64)]
    public string $sessionId;

    #[ORM\Column(type: 'string', length: 16)]
    public string $role;

    #[ORM\Column(type: 'text')]
    public string $content;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    public \DateTimeImmutable $createdAt;

    /**
     * JSON-encoded tool-calling payload, see MessagePayload + MessageMapper.
     * NULL for plain text messages (user, system, plain assistant turns).
     */
    #[ORM\Column(name: 'payload_json', type: 'text', nullable: true)]
    public ?string $payloadJson = null;
}
