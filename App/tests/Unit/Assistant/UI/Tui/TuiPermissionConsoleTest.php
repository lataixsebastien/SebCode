<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\UI\Tui;

use App\Assistant\UI\Tui\TuiPermissionConsole;
use App\Tool\Domain\Model\ValueObject\PermissionChoice;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Only the pure key-mapping is unit-tested; the terminal rendering and the
 * blocking stdin read are inherently TTY-bound and verified manually.
 */
#[CoversClass(TuiPermissionConsole::class)]
final class TuiPermissionConsoleTest extends TestCase
{
    #[DataProvider('keys')]
    public function testMapKeys(string $bytes, ?PermissionChoice $expected): void
    {
        self::assertSame($expected, TuiPermissionConsole::mapKeys($bytes));
    }

    /**
     * @return iterable<string, array{string, ?PermissionChoice}>
     */
    public static function keys(): iterable
    {
        yield 'o → once' => ['o', PermissionChoice::AllowOnce];
        yield 'O uppercase → once' => ['O', PermissionChoice::AllowOnce];
        yield '1 → once' => ['1', PermissionChoice::AllowOnce];
        yield 'a → always' => ['a', PermissionChoice::AllowAlways];
        yield '2 → always' => ['2', PermissionChoice::AllowAlways];
        yield 'r → reject' => ['r', PermissionChoice::Reject];
        yield '3 → reject' => ['3', PermissionChoice::Reject];
        yield 'Escape → reject' => ["\x1b", PermissionChoice::Reject];
        yield 'Ctrl-C → reject' => ["\x03", PermissionChoice::Reject];
        yield 'unknown key → null' => ['x', null];
        yield 'empty → null' => ['', null];
        yield 'first recognised wins in a chunk' => ['zzo', PermissionChoice::AllowOnce];
    }
}
