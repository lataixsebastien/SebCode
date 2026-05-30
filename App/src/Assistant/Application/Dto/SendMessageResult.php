<?php

declare(strict_types=1);

namespace App\Assistant\Application\Dto;

use App\Assistant\Domain\Model\Message;

final readonly class SendMessageResult
{
    /**
     * @param list<Message> $intermediateMessages tool-call request + tool-result messages produced
     *                                            during the agent loop, in chronological order
     *                                            (excludes the user message at the start and the
     *                                            final assistant text reply at the end)
     */
    public function __construct(
        public Message $userMessage,
        public Message $assistantMessage,
        public array $intermediateMessages = [],
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
    ) {
    }
}
