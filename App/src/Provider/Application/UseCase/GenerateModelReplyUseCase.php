<?php

declare(strict_types=1);

namespace SebCode\Provider\Application\UseCase;

use SebCode\Provider\Application\Dto\Input\GenerateModelReplyInput;

final readonly class GenerateModelReplyUseCase
{
    public function __construct(public GenerateModelReplyInput $input)
    {
    }
}
