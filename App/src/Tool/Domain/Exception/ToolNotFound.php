<?php

declare(strict_types=1);

namespace SebCode\Tool\Domain\Exception;

final class ToolNotFound extends \RuntimeException
{
    public static function named(string $name): self
    {
        return new self(sprintf('Tool "%s" was not found.', $name));
    }
}
