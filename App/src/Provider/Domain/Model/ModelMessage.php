<?php

declare(strict_types=1);

namespace SebCode\Provider\Domain\Model;

final readonly class ModelMessage
{
    public function __construct(
        public string $role,
        public string $content,
    ) {
        if ('' === trim($role)) {
            throw new \InvalidArgumentException('Message role must not be empty.');
        }
    }
}
