<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\UI\Tui\Component;

use App\Assistant\UI\Tui\Component\SlashCommands;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SlashCommands::class)]
final class SlashCommandsTest extends TestCase
{
    public function testItemsAllStartWithSlashAndAreDescribed(): void
    {
        $items = SlashCommands::items();

        self::assertNotEmpty($items);
        foreach ($items as $item) {
            self::assertStringStartsWith('/', $item['value']);
            self::assertSame($item['value'], $item['label']);
            self::assertNotSame('', $item['description']);
        }
    }

    #[DataProvider('aliases')]
    public function testNormalizeMapsExitAliases(string $input, string $expected): void
    {
        self::assertSame($expected, SlashCommands::normalize($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function aliases(): iterable
    {
        yield 'quit slash' => ['/quit', SlashCommands::EXIT];
        yield 'vim style' => [':q', SlashCommands::EXIT];
        yield 'plain exit' => ['exit', SlashCommands::EXIT];
        yield 'uppercase QUIT' => ['QUIT', SlashCommands::EXIT];
        yield 'untouched command' => ['/help', '/help'];
        yield 'untouched text' => ['hello world', 'hello world'];
    }

    public function testIsCommand(): void
    {
        self::assertTrue(SlashCommands::isCommand('/help'));
        self::assertTrue(SlashCommands::isCommand(':q'));
        self::assertTrue(SlashCommands::isCommand('  /models '));
        self::assertFalse(SlashCommands::isCommand('/unknown'));
        self::assertFalse(SlashCommands::isCommand('hello'));
    }
}
