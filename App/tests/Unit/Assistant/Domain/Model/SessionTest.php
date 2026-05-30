<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\Domain\Model;

use App\Assistant\Domain\Model\Session;
use App\Assistant\Domain\Model\ValueObject\ModelName;
use App\Assistant\Domain\Model\ValueObject\SessionId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Session::class)]
final class SessionTest extends TestCase
{
    public function testStartCreatesSessionWithCreatedAtEqualUpdatedAt(): void
    {
        $now = new \DateTimeImmutable('2026-05-30 10:00:00');

        $session = Session::start(
            SessionId::fromString('ses_x'),
            ModelName::of('qwen2.5:3b'),
            'Hello',
            $now,
        );

        self::assertSame('ses_x', $session->id->value);
        self::assertSame('qwen2.5:3b', $session->model->value);
        self::assertSame('Hello', $session->title());
        self::assertSame($now, $session->createdAt);
        self::assertSame($now, $session->updatedAt());
        self::assertFalse($session->isArchived());
    }

    public function testRenameUpdatesTitleAndUpdatedAt(): void
    {
        $session = $this->makeSession(new \DateTimeImmutable('2026-05-30 10:00:00'));
        $later = new \DateTimeImmutable('2026-05-30 11:00:00');

        $session->rename('Renamed', $later);

        self::assertSame('Renamed', $session->title());
        self::assertSame($later, $session->updatedAt());
    }

    public function testArchiveSetsFlagAndUpdatedAt(): void
    {
        $session = $this->makeSession(new \DateTimeImmutable('2026-05-30 10:00:00'));
        $later = new \DateTimeImmutable('2026-05-30 12:00:00');

        $session->archive($later);

        self::assertTrue($session->isArchived());
        self::assertSame($later, $session->updatedAt());
    }

    public function testTouchUpdatesUpdatedAtOnly(): void
    {
        $start = new \DateTimeImmutable('2026-05-30 10:00:00');
        $session = $this->makeSession($start);
        $later = new \DateTimeImmutable('2026-05-30 10:00:05');

        $session->touch($later);

        self::assertSame($later, $session->updatedAt());
        self::assertSame($start, $session->createdAt);
        self::assertSame('Original', $session->title());
    }

    private function makeSession(\DateTimeImmutable $now): Session
    {
        return Session::start(
            SessionId::fromString('ses_x'),
            ModelName::of('qwen2.5:3b'),
            'Original',
            $now,
        );
    }
}
