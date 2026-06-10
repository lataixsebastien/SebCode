<?php

declare(strict_types=1);

namespace SebCode\Provider\Domain\Exception;

final class ProviderNotFound extends \RuntimeException
{
    public static function named(string $name): self
    {
        return new self(sprintf('Provider "%s" was not found.', $name));
    }
}
