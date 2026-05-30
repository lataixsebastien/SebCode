<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Clock;

use App\Assistant\Domain\Port\Clock;

final class SystemClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now');
    }
}
