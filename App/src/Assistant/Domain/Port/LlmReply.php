<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Port;

final readonly class LlmReply
{
    public function __construct(
        public string $content,
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
    ) {
    }
}
