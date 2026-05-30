<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\Infrastructure\Persistence\Doctrine\Mapper;

use App\Assistant\Domain\Model\Session;
use App\Assistant\Domain\Model\ValueObject\ModelName;
use App\Assistant\Domain\Model\ValueObject\SessionId;
use App\Assistant\Infrastructure\Persistence\Doctrine\Entity\SessionEntity;
use App\Assistant\Infrastructure\Persistence\Doctrine\Mapper\SessionMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SessionMapper::class)]
final class SessionMapperTest extends TestCase
{
    public function testRoundTripPreservesAllFields(): void
    {
        $mapper = new SessionMapper();
        $now = new \DateTimeImmutable('2026-05-30T10:00:00+00:00');
        $session = Session::start(
            SessionId::fromString('ses_abc'),
            ModelName::of('qwen2.5:3b'),
            'Hello',
            $now,
        );

        $entity = $mapper->toEntity($session);
        $back = $mapper->toAggregate($entity);

        self::assertSame('ses_abc', $back->id->value);
        self::assertSame('qwen2.5:3b', $back->model->value);
        self::assertSame('Hello', $back->title());
        self::assertEquals($now, $back->createdAt);
        self::assertEquals($now, $back->updatedAt());
        self::assertFalse($back->isArchived());
    }

    public function testToEntityReusesExistingInstanceWhenProvided(): void
    {
        $mapper = new SessionMapper();
        $session = Session::start(
            SessionId::fromString('ses_x'),
            ModelName::of('m'),
            't',
            new \DateTimeImmutable(),
        );
        $existing = new SessionEntity();

        $produced = $mapper->toEntity($session, $existing);

        self::assertSame($existing, $produced, 'Mapper must mutate the supplied entity rather than create a new one');
    }

    public function testArchivedSessionRoundTrips(): void
    {
        $mapper = new SessionMapper();
        $now = new \DateTimeImmutable('2026-05-30T10:00:00+00:00');
        $session = Session::start(
            SessionId::fromString('ses_z'),
            ModelName::of('m'),
            't',
            $now,
        );
        $session->archive(new \DateTimeImmutable('2026-05-30T11:00:00+00:00'));

        $back = $mapper->toAggregate($mapper->toEntity($session));

        self::assertTrue($back->isArchived());
    }
}
