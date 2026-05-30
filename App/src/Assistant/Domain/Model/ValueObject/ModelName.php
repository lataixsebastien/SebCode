<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Model\ValueObject;

final readonly class ModelName implements \Stringable
{
    /** @var non-empty-string */
    public string $value;

    public function __construct(string $value)
    {
        if ('' === trim($value)) {
            throw new \InvalidArgumentException('ModelName cannot be empty.');
        }
        $this->value = $value;
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
