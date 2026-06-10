<?php

declare(strict_types=1);

namespace SebCode\Security\Domain;

final class SecretRedactor
{
    /**
     * @var list<string>
     */
    private const SECRET_KEYS = [
        'DATABASE_URL',
        'JWT_SECRET',
        'PRIVATE_KEY',
        'API_KEY',
        'TOKEN',
        'ACCESS_TOKEN',
        'PASSWORD',
    ];

    public function redact(string $text): string
    {
        $text = $this->redactPrivateKeys($text);

        foreach (self::SECRET_KEYS as $key) {
            $text = $this->redactKeyValue($text, $key);
        }

        return $text;
    }

    private function redactPrivateKeys(string $text): string
    {
        $redacted = preg_replace(
            '/-----BEGIN [A-Z ]*PRIVATE KEY-----.*?-----END [A-Z ]*PRIVATE KEY-----/s',
            '[REDACTED PRIVATE KEY]',
            $text,
        );

        return $redacted ?? $text;
    }

    private function redactKeyValue(string $text, string $key): string
    {
        $pattern = '/\b('.preg_quote($key, '/').')\b\s*([:=])\s*("[^"]*"|\'[^\']*\'|[^\s]+)/i';
        $redacted = preg_replace($pattern, '$1$2[REDACTED]', $text);

        return $redacted ?? $text;
    }
}
