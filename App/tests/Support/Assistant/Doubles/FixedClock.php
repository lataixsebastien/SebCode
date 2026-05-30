<?php

declare(strict_types=1);

namespace App\Tests\Support\Assistant\Doubles;

use App\Assistant\Domain\Port\Clock;

final class FixedClock implements Clock
{
    private \DateTimeImmutable $now;

    public function __construct(string $initial = '2026-05-30T10:00:00+00:00')
    {
        $this->now = new \DateTimeImmutable($initial);
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function setTo(string $iso8601): void
    {
        $this->now = new \DateTimeImmutable($iso8601);
    }

    public function advanceSeconds(int $seconds): void
    {
        $this->now = $this->now->modify("+{$seconds} seconds");
    }
}
