<?php

declare(strict_types=1);

namespace SebCode\Workspace\Domain;

final class IgnoreMatcher
{
    /**
     * @param list<string> $patterns
     */
    public function __construct(private readonly array $patterns = self::DEFAULT_PATTERNS)
    {
    }

    public const DEFAULT_PATTERNS = [
        '.env',
        '.env.*',
        '*.pem',
        '*.key',
        '*.p12',
        '*.pfx',
        'id_rsa',
        'id_ed25519',
        'credentials',
        'credentials.*',
        'secrets.*',
        '.auth.json',
        'auth.json',
        '.npmrc',
        '.ssh/**',
        '.aws/**',
        '.gnupg/**',
    ];

    public function isIgnored(string $path): bool
    {
        return null !== $this->matchedPattern($path);
    }

    public function matchedPattern(string $path): ?string
    {
        $normalizedPath = $this->normalize($path);
        $basename = basename($normalizedPath);

        foreach ($this->patterns as $pattern) {
            $normalizedPattern = $this->normalize($pattern);

            if ($this->matches($normalizedPattern, $normalizedPath)) {
                return $pattern;
            }

            if (!str_contains($normalizedPattern, '/') && $this->matches($normalizedPattern, $basename)) {
                return $pattern;
            }
        }

        return null;
    }

    private function normalize(string $path): string
    {
        $normalized = str_replace('\\', '/', trim($path));
        $normalized = preg_replace('#/+#', '/', $normalized);

        return trim($normalized ?? '', '/');
    }

    private function matches(string $pattern, string $path): bool
    {
        $regex = preg_quote($pattern, '#');
        $regex = str_replace('\\*\\*', '.*', $regex);
        $regex = str_replace('\\*', '[^/]*', $regex);

        return 1 === preg_match('#^'.$regex.'$#i', $path);
    }
}
