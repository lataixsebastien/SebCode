<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\Infrastructure\Clock;

use App\Assistant\Infrastructure\Clock\SystemClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SystemClock::class)]
final class SystemClockTest extends TestCase
{
    public function testReturnsAFreshDateTimeImmutable(): void
    {
        $clock = new SystemClock();

        $a = $clock->now();
        $b = $clock->now();

        self::assertInstanceOf(\DateTimeImmutable::class, $a);
        self::assertInstanceOf(\DateTimeImmutable::class, $b);
        self::assertLessThanOrEqual($b, $a, 'now() must be monotonic non-decreasing');
        self::assertLessThan(2, abs($b->getTimestamp() - time()), 'now() must be roughly current time');
    }
}
