<?php

declare(strict_types=1);

namespace SebCode\Provider\Domain\Port;

interface LocalNetworkPolicy
{
    public function assertUrlAllowed(string $url): void;
}
