<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\Application\Command;

use App\Assistant\Application\Command\StartSessionCommand;
use App\Assistant\Application\Command\StartSessionHandler;
use App\Assistant\Domain\Model\ValueObject\ModelName;
use App\Tests\Support\Assistant\Doubles\FixedClock;
use App\Tests\Support\Assistant\Doubles\InMemorySessionRepository;
use App\Tests\Support\Assistant\Doubles\SequenceIdGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StartSessionHandler::class)]
#[CoversClass(StartSessionCommand::class)]
final class StartSessionHandlerTest extends TestCase
{
    public function testCreatesAndPersistsSession(): void
    {
        $repo = new InMemorySessionRepository();
        $ids = new SequenceIdGenerator();
        $clock = new FixedClock('2026-05-30T12:00:00+00:00');
        $handler = new StartSessionHandler($repo, $ids, $clock);

        $command = new StartSessionCommand(
            ModelName::of('qwen2.5:3b'),
            'My first chat',
        );

        $sessionId = $handler($command);

        self::assertSame('ses_001', $sessionId->value);

        $loaded = $repo->findById($sessionId);
        self::assertNotNull($loaded);
        self::assertSame('ses_001', $loaded->id->value);
        self::assertSame('qwen2.5:3b', $loaded->model->value);
        self::assertSame('My first chat', $loaded->title());
        self::assertSame($clock->now(), $loaded->createdAt);
        self::assertSame($clock->now(), $loaded->updatedAt());
        self::assertFalse($loaded->isArchived());
    }

    public function testSequentialCallsProduceDistinctSessions(): void
    {
        $repo = new InMemorySessionRepository();
        $handler = new StartSessionHandler($repo, new SequenceIdGenerator(), new FixedClock());

        $first = $handler(new StartSessionCommand(ModelName::of('m'), 'a'));
        $second = $handler(new StartSessionCommand(ModelName::of('m'), 'b'));

        self::assertSame('ses_001', $first->value);
        self::assertSame('ses_002', $second->value);
        self::assertCount(2, $repo->all());
    }
}
