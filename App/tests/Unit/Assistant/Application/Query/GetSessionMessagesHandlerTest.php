<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\Application\Query;

use App\Assistant\Application\Query\GetSessionMessagesHandler;
use App\Assistant\Application\Query\GetSessionMessagesQuery;
use App\Assistant\Domain\Model\Message;
use App\Assistant\Domain\Model\ValueObject\MessageContent;
use App\Assistant\Domain\Model\ValueObject\MessageId;
use App\Assistant\Domain\Model\ValueObject\MessageRole;
use App\Assistant\Domain\Model\ValueObject\SessionId;
use App\Tests\Support\Assistant\Doubles\InMemoryMessageRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetSessionMessagesHandler::class)]
#[CoversClass(GetSessionMessagesQuery::class)]
final class GetSessionMessagesHandlerTest extends TestCase
{
    public function testReturnsMessagesOfTheGivenSessionInChronologicalOrder(): void
    {
        $repo = new InMemoryMessageRepository();
        $sessionA = SessionId::fromString('ses_A');
        $sessionB = SessionId::fromString('ses_B');

        $repo->append($this->message('msg_1', $sessionA, '2026-05-30T10:00:00+00:00', 'A.first'));
        $repo->append($this->message('msg_2', $sessionB, '2026-05-30T10:00:01+00:00', 'B.foreign'));
        $repo->append($this->message('msg_3', $sessionA, '2026-05-30T10:00:02+00:00', 'A.third'));
        $repo->append($this->message('msg_4', $sessionA, '2026-05-30T10:00:01+00:00', 'A.second'));

        $handler = new GetSessionMessagesHandler($repo);

        $messages = $handler(new GetSessionMessagesQuery($sessionA));

        self::assertSame(
            ['A.first', 'A.second', 'A.third'],
            array_map(static fn (Message $m) => $m->content->text, $messages),
        );
    }

    public function testReturnsEmptyListWhenSessionHasNoMessages(): void
    {
        $handler = new GetSessionMessagesHandler(new InMemoryMessageRepository());

        self::assertSame([], $handler(new GetSessionMessagesQuery(SessionId::fromString('ses_empty'))));
    }

    private function message(string $id, SessionId $sessionId, string $at, string $text): Message
    {
        return new Message(
            MessageId::fromString($id),
            $sessionId,
            MessageRole::User,
            MessageContent::of($text),
            new \DateTimeImmutable($at),
        );
    }
}
