<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\UI\Tui\Component;

use App\Assistant\UI\Tui\Component\PromptHistory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PromptHistory::class)]
final class PromptHistoryTest extends TestCase
{
    public function testPreviousOnEmptyHistoryReturnsNull(): void
    {
        $history = new PromptHistory();

        self::assertNull($history->previous('draft'));
        self::assertFalse($history->isNavigating());
    }

    public function testWalksBackInTime(): void
    {
        $history = new PromptHistory();
        $history->push('first');
        $history->push('second');

        self::assertSame('second', $history->previous(''));
        self::assertSame('first', $history->previous(''));
        // Stays clamped at the oldest entry.
        self::assertSame('first', $history->previous(''));
    }

    public function testNextRestoresDraftPastNewestEntry(): void
    {
        $history = new PromptHistory();
        $history->push('first');
        $history->push('second');

        self::assertSame('second', $history->previous('my draft'));
        self::assertSame('first', $history->previous('my draft'));
        self::assertSame('second', $history->next());
        self::assertSame('my draft', $history->next());
        self::assertFalse($history->isNavigating());
        self::assertNull($history->next());
    }

    public function testPushCollapsesConsecutiveDuplicatesAndIgnoresBlank(): void
    {
        $history = new PromptHistory();
        $history->push('same');
        $history->push('same');
        $history->push('   ');

        self::assertSame('same', $history->previous(''));
        // Only one entry: previous stays on it.
        self::assertSame('same', $history->previous(''));
        self::assertSame('', $history->next());
    }

    public function testPushResetsNavigation(): void
    {
        $history = new PromptHistory();
        $history->push('first');

        self::assertSame('first', $history->previous('draft'));
        $history->push('second');

        self::assertFalse($history->isNavigating());
        self::assertSame('second', $history->previous(''));
    }
}
