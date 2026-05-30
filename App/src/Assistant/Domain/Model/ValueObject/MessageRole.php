<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Model\ValueObject;

enum MessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
    case System = 'system';
    case Tool = 'tool';
}
