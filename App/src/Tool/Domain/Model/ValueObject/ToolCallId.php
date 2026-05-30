<?php

declare(strict_types=1);

namespace App\Tool\Domain\Model\ValueObject;

/**
 * Identifier of a single tool invocation, scoped to a session/message turn.
 *
 * Prefix `tcl_` mirrors the opencode convention (`ses_`, `msg_`, `prt_`).
 */
final readonly class ToolCallId implements \Stringable
{
    public const string PREFIX = 'tcl_';

    private function __construct(public string $value)
    {
        if (!str_starts_with($value, self::PREFIX)) {
            throw new \InvalidArgumentException(\sprintf('ToolCallId must start with "%s", got "%s".', self::PREFIX, $value));
        }
        if (\strlen($value) <= \strlen(self::PREFIX)) {
            throw new \InvalidArgumentException('ToolCallId body cannot be empty.');
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
