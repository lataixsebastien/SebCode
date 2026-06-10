<?php

declare(strict_types=1);

namespace SebCode\Provider\Application\Dto\Input;

use SebCode\Provider\Domain\Model\ModelMessage;

final readonly class ModelMessageInput
{
    public function __construct(
        public string $role,
        public string $content,
    ) {
    }

    public function toDomain(): ModelMessage
    {
        return new ModelMessage($this->role, $this->content);
    }
}
