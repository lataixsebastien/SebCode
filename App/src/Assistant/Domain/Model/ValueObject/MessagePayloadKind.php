<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Model\ValueObject;

enum MessagePayloadKind: string
{
    case ToolCall = 'tool_call';
    case ToolResult = 'tool_result';
}
