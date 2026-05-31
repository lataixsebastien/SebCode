<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Domain\Service;

use App\Tool\Domain\Service\WildcardMatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(WildcardMatcher::class)]
final class WildcardMatcherTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function cases(): iterable
    {
        yield 'exact match' => ['edit', 'edit', true];
        yield 'catch-all star' => ['anything/at/all', '*', true];
        yield 'star matches within segment' => ['git checkout', 'git *', true];
        // Faithful to opencode: `*` becomes `.*` and DOES cross slashes.
        yield 'star crosses slash' => ['src/foo/bar', 'src/*', true];
        yield 'nested path under prefix' => ['src/foo/bar.php', 'src/*', true];
        yield 'question mark single char' => ['cat', 'ca?', true];
        // `?` becomes `.` which also matches a slash (mirrors opencode).
        yield 'question mark matches any single char' => ['a/c', 'a?c', true];
        yield 'literal mismatch' => ['edit', 'bash', false];
        yield 'prefix not enough' => ['gitx', 'git', false];
        yield 'dot is literal' => ['ax', 'a.', false];
        // Trailing " *" optionalises: "ls *" matches the bare command too.
        yield 'trailing star matches bare command' => ['ls', 'ls *', true];
        yield 'trailing star matches with args' => ['ls -la', 'ls *', true];
        yield 'backslashes normalised to slash' => ['src\\foo\\bar', 'src/*', true];
    }

    #[DataProvider('cases')]
    public function testMatches(string $value, string $pattern, bool $expected): void
    {
        self::assertSame($expected, WildcardMatcher::matches($value, $pattern));
    }
}
