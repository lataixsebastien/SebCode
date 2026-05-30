<?php

declare(strict_types=1);

namespace App\Tool\Domain\Exception;

use App\Tool\Domain\Model\ValueObject\ToolName;

final class InvalidToolArguments extends \InvalidArgumentException
{
    public static function for(ToolName $name, string $reason): self
    {
        return new self(\sprintf('Tool "%s" received invalid arguments: %s', $name->value, $reason));
    }
}
