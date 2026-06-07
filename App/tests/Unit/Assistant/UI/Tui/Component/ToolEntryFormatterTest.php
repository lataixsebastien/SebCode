<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\UI\Tui\Component;

use App\Assistant\UI\Tui\Component\ToolEntryFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolEntryFormatter::class)]
final class ToolEntryFormatterTest extends TestCase
{
    private ToolEntryFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new ToolEntryFormatter();
    }

    public function testCallLineUsesDisplayNameAndSubjectKey(): void
    {
        self::assertSame(
            '⚙ Bash  ls -la',
            $this->formatter->callLine('bash', ['command' => 'ls -la']),
        );
        self::assertSame(
            '⚙ Read  src/Kernel.php',
            $this->formatter->callLine('read', ['filePath' => 'src/Kernel.php']),
        );
        self::assertSame(
            '⚙ Patch',
            $this->formatter->callLine('apply_patch', []),
        );
    }

    public function testCallLineFallsBackToFirstStringArgument(): void
    {
        self::assertSame(
            '⚙ Todo  something',
            $this->formatter->callLine('todowrite', ['items' => ['x'], 'note' => 'something']),
        );
    }

    public function testCallLineCollapsesWhitespaceAndTruncatesLongSubjects(): void
    {
        $line = $this->formatter->callLine('bash', ['command' => "echo   'a'\n&& ".str_repeat('x', 200)]);

        self::assertStringStartsWith("⚙ Bash  echo 'a' && ", $line);
        self::assertStringEndsWith('…', $line);
        self::assertLessThanOrEqual(90, mb_strlen($line));
    }

    public function testResultTextTruncatesToMaxLines(): void
    {
        $output = implode("\n", array_map(static fn (int $i): string => "line $i", range(1, 10)));

        $text = $this->formatter->resultText('bash', $output, false);

        self::assertStringStartsWith('  ✓ Bash', $text);
        self::assertStringContainsString('    line 1', $text);
        self::assertStringContainsString('    line 6', $text);
        self::assertStringNotContainsString('line 7', $text);
        self::assertStringContainsString('… +4 lines', $text);
    }

    public function testResultTextMarksErrorsAndEmptyOutput(): void
    {
        self::assertStringStartsWith('  ✗ Grep', $this->formatter->resultText('grep', 'boom', true));
        self::assertSame('  ✓ Glob (no output)', $this->formatter->resultText('glob', "  \n ", false));
    }
}
