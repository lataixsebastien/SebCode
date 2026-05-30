<?php

declare(strict_types=1);

namespace App\Tool\Domain\Model\ValueObject;

/**
 * Public name of a tool as advertised to the LLM (e.g. "glob", "read").
 *
 * Must match `[a-z_][a-z0-9_]{0,63}` — the same regex Anthropic/OpenAI use
 * for function names. Keeps things wire-compatible across providers.
 */
final readonly class ToolName implements \Stringable
{
    private const string PATTERN = '/^[a-z_][a-z0-9_]{0,63}$/';

    private function __construct(public string $value)
    {
        if (1 !== preg_match(self::PATTERN, $value)) {
            throw new \InvalidArgumentException(\sprintf('Invalid tool name "%s"; must match %s.', $value, self::PATTERN));
        }
    }

    public static function of(string $value): self
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
