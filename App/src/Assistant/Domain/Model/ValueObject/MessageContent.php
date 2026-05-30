<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Model\ValueObject;

final readonly class MessageContent implements \Stringable
{
    public function __construct(public string $text)
    {
    }

    public static function of(string $text): self
    {
        return new self($text);
    }

    public function isEmpty(): bool
    {
        return '' === trim($this->text);
    }

    public function __toString(): string
    {
        return $this->text;
    }
}
