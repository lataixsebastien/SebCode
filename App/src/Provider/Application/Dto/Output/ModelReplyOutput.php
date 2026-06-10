<?php

declare(strict_types=1);

namespace SebCode\Provider\Application\Dto\Output;

use SebCode\Provider\Domain\Model\ModelReply;
use SebCode\Provider\Domain\Model\ToolCall;

final readonly class ModelReplyOutput
{
    /**
     * @param list<ToolCallOutput> $toolCalls
     */
    public function __construct(
        public string $content,
        public array $toolCalls = [],
    ) {
    }

    public static function fromDomain(ModelReply $reply): self
    {
        return new self(
            $reply->content,
            array_map(
                static fn (ToolCall $toolCall): ToolCallOutput => ToolCallOutput::fromDomain($toolCall),
                $reply->toolCalls,
            ),
        );
    }
}
