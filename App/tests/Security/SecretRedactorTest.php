<?php

declare(strict_types=1);

namespace SebCode\Tests\Security;

use PHPUnit\Framework\TestCase;
use SebCode\Security\Domain\SecretRedactor;

final class SecretRedactorTest extends TestCase
{
    public function testItMasksDatabaseUrlApiKeyAndToken(): void
    {
        $redactor = new SecretRedactor();

        $redacted = $redactor->redact('DATABASE_URL=mysql://user:pass@db API_KEY=abc123 token: secret-token');

        self::assertStringContainsString('DATABASE_URL=[REDACTED]', $redacted);
        self::assertStringContainsString('API_KEY=[REDACTED]', $redacted);
        self::assertStringContainsString('token:[REDACTED]', $redacted);
        self::assertStringNotContainsString('mysql://user:pass@db', $redacted);
        self::assertStringNotContainsString('abc123', $redacted);
        self::assertStringNotContainsString('secret-token', $redacted);
    }

    public function testItMasksPrivateKeys(): void
    {
        $redactor = new SecretRedactor();
        $privateKey = "-----BEGIN PRIVATE KEY-----\nabc123\n-----END PRIVATE KEY-----";

        $redacted = $redactor->redact('key='.$privateKey);

        self::assertStringContainsString('[REDACTED PRIVATE KEY]', $redacted);
        self::assertStringNotContainsString('abc123', $redacted);
    }
}
