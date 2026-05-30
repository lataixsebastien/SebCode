<?php

declare(strict_types=1);

namespace App\Assistant\Application\Dto;

use App\Assistant\Domain\Model\Message;

final readonly class SendMessageResult
{
    public function __construct(
        public Message $userMessage,
        public Message $assistantMessage,
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
    ) {
    }
}
