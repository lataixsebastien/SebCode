<?php

declare(strict_types=1);

namespace SebCode\Tool\Application\UseCase;

final readonly class ListToolsUseCase
{
    public function __construct(public ?string $mode = null)
    {
    }
}
