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

    #[DataProvider('navigation')]
    public function testMapNavigation(string $bytes, ?string $expected): void
    {
        self::assertSame($expected, TuiPermissionConsole::mapNavigation($bytes));
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function navigation(): iterable
    {
        yield 'left arrow (CSI)' => ["\x1b[D", 'left'];
        yield 'left arrow (SS3)' => ["\x1bOD", 'left'];
        yield 'right arrow (CSI)' => ["\x1b[C", 'right'];
        yield 'right arrow (SS3)' => ["\x1bOC", 'right'];
        yield 'tab cycles right' => ["\t", 'right'];
        yield 'carriage return → enter' => ["\r", 'enter'];
        yield 'newline → enter' => ["\n", 'enter'];
        yield 'bare escape is not navigation' => ["\x1b", null];
        yield 'letter is not navigation' => ['o', null];
    }
}
