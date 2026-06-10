<?php

declare(strict_types=1);

namespace SebCode\Tests\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SebCode\Security\Domain\Exception\SecurityViolation;
use SebCode\Security\Domain\NetworkPolicy;

final class NetworkPolicyTest extends TestCase
{
    #[DataProvider('allowedLocalUrls')]
    public function testItAllowsLocalUrls(string $url): void
    {
        $policy = new NetworkPolicy();

        self::assertTrue($policy->isUrlAllowed($url));
        $policy->assertUrlAllowed($url);
    }

    #[DataProvider('blockedUrls')]
    public function testItBlocksExternalOrUnsupportedUrls(string $url): void
    {
        $policy = new NetworkPolicy();

        self::assertFalse($policy->isUrlAllowed($url));

        $this->expectException(SecurityViolation::class);
        $policy->assertUrlAllowed($url);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function allowedLocalUrls(): iterable
    {
        yield 'localhost' => ['http://localhost:11434/api/generate'];
        yield 'ipv4 loopback' => ['http://127.0.0.1:11434/api/generate'];
        yield 'ipv6 loopback' => ['http://[::1]:11434/api/generate'];
        yield 'docker host' => ['http://host.docker.internal:11434/api/generate'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blockedUrls(): iterable
    {
        yield 'external http' => ['https://example.com'];
        yield 'ftp' => ['ftp://localhost/file'];
        yield 'ssh' => ['ssh://localhost/repo.git'];
        yield 'missing host' => ['not-a-url'];
    }
}
