<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\Infrastructure\Id;

use App\Assistant\Domain\Model\ValueObject\MessageId;
use App\Assistant\Domain\Model\ValueObject\SessionId;
use App\Assistant\Infrastructure\Id\SymfonyUidGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SymfonyUidGenerator::class)]
final class SymfonyUidGeneratorTest extends TestCase
{
    public function testNextSessionIdProducesValidPrefixedId(): void
    {
        $gen = new SymfonyUidGenerator();

        $id = $gen->nextSessionId();

        self::assertInstanceOf(SessionId::class, $id);
        self::assertStringStartsWith(SessionId::PREFIX, $id->value);
        self::assertGreaterThan(\strlen(SessionId::PREFIX), \strlen($id->value));
    }

    public function testNextMessageIdProducesValidPrefixedId(): void
    {
        $gen = new SymfonyUidGenerator();

        $id = $gen->nextMessageId();

        self::assertInstanceOf(MessageId::class, $id);
        self::assertStringStartsWith(MessageId::PREFIX, $id->value);
    }

    public function testSuccessiveIdsAreUnique(): void
    {
        $gen = new SymfonyUidGenerator();

        $ids = [];
        for ($i = 0; $i < 100; ++$i) {
            $ids[] = $gen->nextSessionId()->value;
        }

        self::assertCount(100, array_unique($ids));
    }

    public function testIdsAreTimeOrdered(): void
    {
        $gen = new SymfonyUidGenerator();

        $first = $gen->nextSessionId()->value;
        usleep(2000);
        $second = $gen->nextSessionId()->value;

        self::assertLessThan(0, strcmp($first, $second), 'UUIDv7-based ids must sort chronologically');
    }
}
