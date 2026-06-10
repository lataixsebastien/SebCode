<?php

declare(strict_types=1);

namespace SebCode\Provider\Domain\Model;

final readonly class ModelReply
{
    /**
     * @param list<ToolCall> $toolCalls
     */
    public function __construct(
        public string $content,
        public array $toolCalls = [],
    ) {
    }
}
