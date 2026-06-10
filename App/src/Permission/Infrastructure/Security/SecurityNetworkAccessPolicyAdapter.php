<?php

declare(strict_types=1);

namespace SebCode\Permission\Infrastructure\Security;

use SebCode\Permission\Domain\Port\NetworkAccessPolicy;
use SebCode\Security\Domain\NetworkPolicy;

final readonly class SecurityNetworkAccessPolicyAdapter implements NetworkAccessPolicy
{
    public function __construct(private NetworkPolicy $networkPolicy)
    {
    }

    public function assertUrlAllowed(string $url): void
    {
        $this->networkPolicy->assertUrlAllowed($url);
    }
}
