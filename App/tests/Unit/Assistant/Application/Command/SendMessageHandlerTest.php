<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\Application\Command;

use App\Assistant\Application\Command\SendMessageCommand;
use App\Assistant\Application\Command\SendMessageHandler;
use App\Assistant\Application\Dto\SendMessageResult;
use App\Assistant\Domain\Exception\AgentLoopExceeded;
use App\Assistant\Domain\Exception\InvalidArgument;
use App\Assistant\Domain\Exception\LlmUnavailable;
use App\Assistant\Domain\Exception\SessionNotFound;
use App\Assistant\Domain\Model\Session;
use App\Assistant\Domain\Model\ValueObject\MessagePayloadKind;
use App\Assistant\Domain\Model\ValueObject\MessageRole;
use App\Assistant\Domain\Model\ValueObject\ModelName;
use App\Assistant\Domain\Model\ValueObject\SessionId;
use App\Assistant\Domain\Model\ValueObject\ToolAdvertisement;
use App\Assistant\Domain\Model\ValueObject\ToolCallRequest;
use App\Assistant\Domain\Model\ValueObject\ToolResultDto;
use App\Assistant\Infrastructure\Stream\MutableAgentOutputStreamRegistry;
use App\Assistant\Infrastructure\Stream\NullAgentOutputStream;
use App\Tests\Support\Assistant\Doubles\FakeAgentOutputStream;
use App\Tests\Support\Assistant\Doubles\FixedClock;
use App\Tests\Support\Assistant\Doubles\FixedSystemPrompt;
use App\Tests\Support\Assistant\Doubles\InMemoryMessageRepository;
use App\Tests\Support\Assistant\Doubles\InMemorySessionRepository;
use App\Tests\Support\Assistant\Doubles\RecordingToolGateway;
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
    private RecordingToolGateway $toolGateway;
    private SequenceIdGenerator $ids;
    private FixedClock $clock;
    private MutableAgentOutputStreamRegistry $streams;
    private SendMessageHandler $handler;
    private SessionId $sessionId;

    protected function setUp(): void
    {
        $this->sessions = new InMemorySessionRepository();
        $this->messages = new InMemoryMessageRepository();
        $this->llm = new ScriptedLlm();
        $this->toolGateway = new RecordingToolGateway();
        $this->ids = new SequenceIdGenerator();
        $this->clock = new FixedClock('2026-05-30T12:00:00+00:00');
        $this->streams = new MutableAgentOutputStreamRegistry(new NullAgentOutputStream());
        $this->handler = new SendMessageHandler(
            $this->sessions,
            $this->messages,
            $this->llm,
            $this->toolGateway,
            $this->ids,
            $this->clock,
            new FixedSystemPrompt('SYSTEM PROMPT'),
            $this->streams,
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
        self::assertSame([], $result->intermediateMessages);

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
        // The system prompt always leads, then the freshly-appended user message.
        self::assertCount(2, $conversation);
        self::assertSame(MessageRole::System, $conversation[0]->role);
        self::assertSame('SYSTEM PROMPT', $conversation[0]->content->text);
        self::assertSame(MessageRole::User, $conversation[1]->role);
        self::assertSame('first question', $conversation[1]->content->text);
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
        self::assertCount(4, $secondHistory, 'second LLM call should see the system prompt then Q1, A1, Q2');
        self::assertSame([
            [MessageRole::System, 'SYSTEM PROMPT'],
            [MessageRole::User, 'Q1'],
            [MessageRole::Assistant, 'A1'],
            [MessageRole::User, 'Q2'],
        ], array_map(
            static fn ($m) => [$m->role, $m->content->text],
            $secondHistory,
        ));
    }

    public function testSystemPromptIsPrependedButNeverPersisted(): void
    {
        $this->llm->scriptReply('ok');

        ($this->handler)(new SendMessageCommand($this->sessionId, 'hi'));

        // The LLM sees the system prompt first…
        self::assertSame(MessageRole::System, $this->llm->calls()[0]['conversation'][0]->role);

        // …but it is never written to the stored conversation.
        foreach ($this->messages->forSession($this->sessionId) as $message) {
            self::assertNotSame('SYSTEM PROMPT', $message->content->text);
        }
    }

    public function testStreamsTextAndToolEventsToTheAttachedSink(): void
    {
        $sink = new FakeAgentOutputStream();
        $this->streams->attach($sink);

        $this->llm->scriptToolCallTurn([new ToolCallRequest('0', 'read', ['filePath' => 'x'])]);
        $this->llm->scriptReply('final answer');

        ($this->handler)(new SendMessageCommand($this->sessionId, 'go'));

        self::assertSame(['read'], $sink->toolCalls);
        self::assertCount(1, $sink->toolResults);
        self::assertContains('final answer', $sink->texts, 'The final assistant text is streamed live.');
    }

    public function testUserInterruptStopsTheLoopBeforeRunningToolCalls(): void
    {
        $sink = new FakeAgentOutputStream();
        $sink->interrupted = true;
        $this->streams->attach($sink);

        // Even though the model asked for a tool call, the interrupt must end the
        // turn before it runs, and the loop must not request a second reply.
        $this->llm->scriptToolCallTurn([new ToolCallRequest('0', 'read', ['filePath' => 'x'])]);
        $this->llm->scriptReply('should never be reached');

        $result = ($this->handler)(new SendMessageCommand($this->sessionId, 'go'));

        self::assertTrue($result->interrupted);
        self::assertSame([], $sink->toolCalls, 'No tool runs after an interrupt.');
        self::assertCount(1, $this->llm->calls(), 'The loop stops after the interrupted turn.');
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

    public function testSingleToolCallTriggersExecuteThenFinalText(): void
    {
        $this->toolGateway->advertise(new ToolAdvertisement('glob', 'desc', ['type' => 'object']));
        $this->toolGateway->scriptResult(ToolResultDto::success('tcl_1', '3 files matched'));

        $this->llm->scriptToolCallTurn([new ToolCallRequest('tcl_1', 'glob', ['pattern' => '*.php'])]);
        $this->llm->scriptReply('Here are the files: ...', promptTokens: 50, completionTokens: 12);

        $result = ($this->handler)(new SendMessageCommand($this->sessionId, 'list files'));

        self::assertSame('Here are the files: ...', $result->assistantMessage->content->text);
        self::assertCount(2, $result->intermediateMessages);
        self::assertSame(MessageRole::Assistant, $result->intermediateMessages[0]->role);
        self::assertNotNull($result->intermediateMessages[0]->payload);
        self::assertSame(MessagePayloadKind::ToolCall, $result->intermediateMessages[0]->payload->kind);
        self::assertSame(MessageRole::Tool, $result->intermediateMessages[1]->role);
        self::assertNotNull($result->intermediateMessages[1]->payload);
        self::assertSame(MessagePayloadKind::ToolResult, $result->intermediateMessages[1]->payload->kind);
        self::assertSame('3 files matched', $result->intermediateMessages[1]->payload->toolOutput);

        // 2 LLM calls (1st returned tool_calls, 2nd returned final text)
        self::assertCount(2, $this->llm->calls());
        // ToolGateway received exactly the one call
        self::assertCount(1, $this->toolGateway->executions);
        self::assertSame('glob', $this->toolGateway->executions[0]->name);

        // Persisted: user + assistant(tool_calls) + tool(result) + assistant(final) = 4
        self::assertCount(4, $this->messages->forSession($this->sessionId));
    }

    public function testToolGatewayExceptionAbortsLoopWithoutLosingHistory(): void
    {
        $this->toolGateway->advertise(new ToolAdvertisement('glob', 'desc', ['type' => 'object']));
        $this->toolGateway->scriptException(new \RuntimeException('registry broken'));
        $this->llm->scriptToolCallTurn([new ToolCallRequest('tcl_1', 'glob', [])]);

        try {
            ($this->handler)(new SendMessageCommand($this->sessionId, 'go'));
            self::fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('registry broken', $e->getMessage());
        }

        // User + assistant(tool_calls) must remain so the operator can inspect.
        $persisted = $this->messages->forSession($this->sessionId);
        self::assertGreaterThanOrEqual(2, \count($persisted));
        self::assertSame(MessageRole::User, $persisted[0]->role);
    }

    public function testMaxTurnsThrowsAgentLoopExceeded(): void
    {
        $this->toolGateway->advertise(new ToolAdvertisement('noop', 'desc', ['type' => 'object']));
        // The LLM keeps requesting different tool calls forever; doom-loop guard
        // doesn't trigger (signatures differ). MAX_TURNS catches it instead.
        for ($i = 1; $i <= SendMessageHandler::MAX_TURNS + 2; ++$i) {
            $this->llm->scriptToolCallTurn([new ToolCallRequest('tcl_'.$i, 'noop', ['n' => $i])]);
        }

        $this->expectException(AgentLoopExceeded::class);
        ($this->handler)(new SendMessageCommand($this->sessionId, 'spin'));
    }

    public function testDoomLoopGuardForcesTextualReplyOnIdenticalRepeatedToolCall(): void
    {
        $this->toolGateway->advertise(new ToolAdvertisement('glob', 'desc', ['type' => 'object']));
        // Three identical tool calls in a row — triggers the guard.
        for ($i = 1; $i <= SendMessageHandler::DOOM_LOOP_THRESHOLD; ++$i) {
            $this->llm->scriptToolCallTurn([new ToolCallRequest('tcl_'.$i, 'glob', ['pattern' => 'x'])]);
        }
        // After the guard fires, the handler advertises no tools — the LLM
        // commits to a textual answer.
        $this->llm->scriptReply('Done — here is what I found.');

        $result = ($this->handler)(new SendMessageCommand($this->sessionId, 'spin'));

        self::assertSame('Done — here is what I found.', $result->assistantMessage->content->text);

        $lastCall = $this->llm->calls()[\count($this->llm->calls()) - 1];
        self::assertSame([], $lastCall['tools'], 'tools must be dropped after the doom-loop guard fires');
    }

    public function testToolSoftFailureFedBackToLlm(): void
    {
        $this->toolGateway->advertise(new ToolAdvertisement('read', 'desc', ['type' => 'object']));
        $this->toolGateway->scriptResult(ToolResultDto::error('tcl_1', 'file not found'));

        $this->llm->scriptToolCallTurn([new ToolCallRequest('tcl_1', 'read', ['filePath' => 'nope'])]);
        $this->llm->scriptReply('Sorry, the file does not exist.');

        $result = ($this->handler)(new SendMessageCommand($this->sessionId, 'read it'));

        self::assertSame('Sorry, the file does not exist.', $result->assistantMessage->content->text);
        // The tool-result message captured the soft failure.
        $toolMsg = $result->intermediateMessages[1];
        self::assertNotNull($toolMsg->payload);
        self::assertTrue($toolMsg->payload->isError);
        self::assertSame('file not found', $toolMsg->payload->toolOutput);
    }
}
