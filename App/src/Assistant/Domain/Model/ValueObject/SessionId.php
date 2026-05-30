<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Model\ValueObject;

final readonly class SessionId implements \Stringable
{
    public const string PREFIX = 'ses_';

    private function __construct(public string $value)
    {
        if (!str_starts_with($value, self::PREFIX)) {
            throw new \InvalidArgumentException(\sprintf('SessionId must start with "%s", got "%s".', self::PREFIX, $value));
        }
        if (\strlen($value) <= \strlen(self::PREFIX)) {
            throw new \InvalidArgumentException('SessionId body cannot be empty.');
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
