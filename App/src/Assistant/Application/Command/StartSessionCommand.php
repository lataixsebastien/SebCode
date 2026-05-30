<?php

declare(strict_types=1);

namespace App\Assistant\Application\Command;

use App\Assistant\Domain\Model\ValueObject\ModelName;

final readonly class StartSessionCommand
{
    public function __construct(
        public ModelName $model,
        public string $title,
    ) {
    }
}
