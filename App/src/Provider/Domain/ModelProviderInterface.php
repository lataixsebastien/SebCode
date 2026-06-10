<?php

declare(strict_types=1);

namespace SebCode\Provider\Domain;

use SebCode\Provider\Domain\Model\ModelReply;
use SebCode\Provider\Domain\Model\ModelRequest;

interface ModelProviderInterface
{
    public function name(): string;

    public function generate(ModelRequest $request): ModelReply;
}
