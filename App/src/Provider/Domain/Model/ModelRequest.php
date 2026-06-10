<?php

declare(strict_types=1);

namespace SebCode\Provider\Domain\Model;

final readonly class ModelRequest
{
    /**
     * @param list<ModelMessage> $messages
     */
    public function __construct(
        public string $model,
        public array $messages,
    ) {
        if ('' === trim($model)) {
            throw new \InvalidArgumentException('Model name must not be empty.');
        }

        if ([] === $messages) {
            throw new \InvalidArgumentException('Model request must contain at least one message.');
        }
    }
}
