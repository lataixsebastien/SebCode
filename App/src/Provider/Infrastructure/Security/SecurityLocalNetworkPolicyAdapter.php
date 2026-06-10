<?php

declare(strict_types=1);

namespace SebCode\Provider\Infrastructure\Security;

use SebCode\Provider\Domain\Port\LocalNetworkPolicy;
use SebCode\Security\Domain\NetworkPolicy;

final readonly class SecurityLocalNetworkPolicyAdapter implements LocalNetworkPolicy
{
    public function __construct(private NetworkPolicy $networkPolicy)
    {
    }

    public function assertUrlAllowed(string $url): void
    {
        $this->networkPolicy->assertUrlAllowed($url);
    }
}
