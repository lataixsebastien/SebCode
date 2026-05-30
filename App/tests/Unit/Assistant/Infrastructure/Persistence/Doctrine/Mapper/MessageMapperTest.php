<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\Infrastructure\Persistence\Doctrine\Mapper;

use App\Assistant\Domain\Model\Message;
use App\Assistant\Domain\Model\ValueObject\MessageContent;
use App\Assistant\Domain\Model\ValueObject\MessageId;
use App\Assistant\Domain\Model\ValueObject\MessageRole;
use App\Assistant\Domain\Model\ValueObject\SessionId;
use App\Assistant\Infrastructure\Persistence\Doctrine\Mapper\MessageMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageMapper::class)]
final class MessageMapperTest extends TestCase
{
    #[DataProvider('roles')]
    public function testRoundTripPreservesAllFields(MessageRole $role): void
    {
        $mapper = new MessageMapper();
        $at = new \DateTimeImmutable('2026-05-30T10:00:00+00:00');
        $original = new Message(
            MessageId::fromString('msg_42'),
            SessionId::fromString('ses_42'),
            $role,
            MessageContent::of('multi-\nline body'),
            $at,
        );

        $back = $mapper->toAggregate($mapper->toEntity($original));

        self::assertSame('msg_42', $back->id->value);
        self::assertSame('ses_42', $back->sessionId->value);
        self::assertSame($role, $back->role);
        self::assertSame('multi-\nline body', $back->content->text);
        self::assertEquals($at, $back->createdAt);
    }

    /**
     * @return iterable<string, array{MessageRole}>
     */
    public static function roles(): iterable
    {
        yield 'user' => [MessageRole::User];
        yield 'assistant' => [MessageRole::Assistant];
        yield 'system' => [MessageRole::System];
        yield 'tool' => [MessageRole::Tool];
    }
}
