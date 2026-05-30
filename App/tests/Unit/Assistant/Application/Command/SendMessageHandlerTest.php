<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\Application\Command;

use App\Assistant\Application\Command\SendMessageCommand;
use App\Assistant\Application\Command\SendMessageHandler;
use App\Assistant\Application\Dto\SendMessageResult;
use App\Assistant\Domain\Exception\InvalidArgument;
use App\Assistant\Domain\Exception\LlmUnavailable;
use App\Assistant\Domain\Exception\SessionNotFound;
use App\Assistant\Domain\Model\Session;
use App\Assistant\Domain\Model\ValueObject\MessageRole;
use App\Assistant\Domain\Model\ValueObject\ModelName;
use App\Assistant\Domain\Model\ValueObject\SessionId;
use App\Tests\Support\Assistant\Doubles\FixedClock;
use App\Tests\Support\Assistant\Doubles\InMemoryMessageRepository;
use App\Tests\Support\Assistant\Doubles\InMemorySessionRepository;
use App\Tests\Support\Assistant\Doubles\ScriptedLlm;
use App\Tests\Support\Assistant\Doubles\SequenceIdGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SendMessageHandler::class)]
#[CoversClass(SendMessageCommand::class)]
#[CoversClass(SendMessageResult::class)]
final class SendMessageHandlerTest extends TestCase
{
    private InMemorySessionRepository $sessions;
    private InMemoryMessageRepository $messages;
    private ScriptedLlm $llm;
    private SequenceIdGenerator $ids;
    private FixedClock $clock;
    private SendMessageHandler $handler;
    private SessionId $sessionId;

    protected function setUp(): void
    {
        $this->sessions = new InMemorySessionRepository();
        $this->messages = new InMemoryMessageRepository();
        $this->llm = new ScriptedLlm();
        $this->ids = new SequenceIdGenerator();
        $this->clock = new FixedClock('2026-05-30T12:00:00+00:00');
        $this->handler = new SendMessageHandler(
            $this->sessions,
            $this->messages,
            $this->llm,
            $this->ids,
            $this->clock,
        );

        $this->sessionId = SessionId::fromString('ses_existing');
        $this->sessions->save(Session::start(
            $this->sessionId,
            ModelName::of('qwen2.5:3b'),
            'Test',
            new \DateTimeImmutable('2026-05-30T11:00:00+00:00'),
        ));
    }

    public function testAppendsUserMessageCallsLlmAndAppendsAssistantReply(): void
    {
        $this->llm->scriptReply('Hello back!', promptTokens: 12, completionTokens: 4);

        $result = ($this->handler)(new SendMessageCommand($this->sessionId, 'Hello'));

        self::assertInstanceOf(SendMessageResult::class, $result);
        self::assertSame(MessageRole::User, $result->userMessage->role);
        self::assertSame('Hello', $result->userMessage->content->text);
        self::assertSame(MessageRole::Assistant, $result->assistantMessage->role);
        self::assertSame('Hello back!', $result->assistantMessage->content->text);
        self::assertSame(12, $result->promptTokens);
        self::assertSame(4, $result->completionTokens);

        $persisted = $this->messages->forSession($this->sessionId);
        self::assertCount(2, $persisted);
        self::assertSame('Hello', $persisted[0]->content->text);
        self::assertSame('Hello back!', $persisted[1]->content->text);
    }

    public function testLlmReceivesFullHistoryIncludingTheNewUserMessage(): void
    {
        $this->llm->scriptReply('reply');

        ($this->handler)(new SendMessageCommand($this->sessionId, 'first question'));

        $calls = $this->llm->calls();
        self::assertCount(1, $calls);
        self::assertSame('qwen2.5:3b', $calls[0]['model']->value);
        $conversation = $calls[0]['conversation'];
        self::assertCount(1, $conversation, 'LLM should see only the freshly-appended user message on first turn');
        self::assertSame(MessageRole::User, $conversation[0]->role);
        self::assertSame('first question', $conversation[0]->content->text);
    }

    public function testSecondTurnSendsCompleteHistoryToLlm(): void
    {
        $this->llm->scriptReply('A1');
        ($this->handler)(new SendMessageCommand($this->sessionId, 'Q1'));

        $this->clock->advanceSeconds(30);
        $this->llm->scriptReply('A2');
        ($this->handler)(new SendMessageCommand($this->sessionId, 'Q2'));

        $calls = $this->llm->calls();
        self::assertCount(2, $calls);
        $secondHistory = $calls[1]['conversation'];
        self::assertCount(3, $secondHistory, 'second LLM call should see Q1, A1 and the new Q2');
        self::assertSame([
            [MessageRole::User, 'Q1'],
            [MessageRole::Assistant, 'A1'],
            [MessageRole::User, 'Q2'],
        ], array_map(
            static fn ($m) => [$m->role, $m->content->text],
            $secondHistory,
        ));
    }

    public function testSessionUpdatedAtIsBumped(): void
    {
        $this->llm->scriptReply('x');

        ($this->handler)(new SendMessageCommand($this->sessionId, 'Hello'));

        $session = $this->sessions->findById($this->sessionId);
        self::assertNotNull($session);
        self::assertSame($this->clock->now(), $session->updatedAt());
    }

    public function testThrowsWhenSessionDoesNotExist(): void
    {
        $this->expectException(SessionNotFound::class);
        ($this->handler)(new SendMessageCommand(SessionId::fromString('ses_ghost'), 'Hello'));
    }

    public function testThrowsWhenUserTextIsEmpty(): void
    {
        $this->expectException(InvalidArgument::class);
        ($this->handler)(new SendMessageCommand($this->sessionId, "   \t  "));
    }

    public function testPropagatesLlmFailureAndKeepsUserMessage(): void
    {
        $this->llm->scriptFailure(LlmUnavailable::fromUpstream('Ollama down'));

        try {
            ($this->handler)(new SendMessageCommand($this->sessionId, 'Hello'));
            self::fail('Expected LlmUnavailable was not thrown');
        } catch (LlmUnavailable $e) {
            self::assertStringContainsString('Ollama down', $e->getMessage());
        }

        $persisted = $this->messages->forSession($this->sessionId);
        self::assertCount(1, $persisted, 'user message must remain so it can be retried');
        self::assertSame(MessageRole::User, $persisted[0]->role);
    }
}
