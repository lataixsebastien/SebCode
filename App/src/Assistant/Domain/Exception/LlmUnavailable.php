<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Exception;

final class LlmUnavailable extends \RuntimeException
{
    public static function fromUpstream(string $reason, ?\Throwable $previous = null): self
    {
        return new self(\sprintf('LLM unavailable: %s', $reason), 0, $previous);
    }
}
