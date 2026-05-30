<?php

declare(strict_types=1);

namespace App\Tool\Domain\Exception;

use App\Tool\Domain\Model\ValueObject\ToolName;

final class ToolNotFound extends \RuntimeException
{
    public static function withName(ToolName $name): self
    {
        return new self(\sprintf('No tool registered under the name "%s".', $name->value));
    }
}
