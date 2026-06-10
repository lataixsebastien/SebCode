<?php

declare(strict_types=1);

namespace SebCode\Security\Domain;

use SebCode\Security\Domain\Exception\SecurityViolation;

final class NetworkPolicy
{
    /**
     * @param list<string> $allowedHosts
     * @param list<string> $allowedSchemes
     */
    public function __construct(
        private readonly array $allowedHosts = ['localhost', '127.0.0.1', '::1', 'host.docker.internal'],
        private readonly array $allowedSchemes = ['http', 'https'],
    ) {
    }

    public function assertUrlAllowed(string $url): void
    {
        if (!$this->isUrlAllowed($url)) {
            throw new SecurityViolation(sprintf('Network access to "%s" is not allowed.', $url));
        }
    }

    public function isUrlAllowed(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        if (!is_string($scheme) || !is_string($host)) {
            return false;
        }

        $scheme = strtolower($scheme);
        $host = strtolower(trim($host, '[]'));

        if (!in_array($scheme, $this->allowedSchemes, true)) {
            return false;
        }

        return in_array($host, $this->normalizedAllowedHosts(), true);
    }

    /**
     * @return list<string>
     */
    private function normalizedAllowedHosts(): array
    {
        return array_map(
            static fn (string $host): string => strtolower(trim($host, '[]')),
            $this->allowedHosts,
        );
    }
}
