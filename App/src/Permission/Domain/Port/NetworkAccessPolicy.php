<?php

declare(strict_types=1);

namespace SebCode\Permission\Domain\Port;

interface NetworkAccessPolicy
{
    public function assertUrlAllowed(string $url): void;
}
