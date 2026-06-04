<?php

declare(strict_types=1);

namespace App\Assistant\Application\Command;

use App\Assistant\Application\Dto\SendMessageResult;
use App\Assistant\Domain\Exception\AgentLoopExceeded;
use App\Assistant\Domain\Exception\InvalidArgument;
use App\Assistant\Domain\Exception\SessionNotFound;
use App\Assistant\Domain\Model\Message;
use App\Assistant\Domain\Model\Session;
use App\Assistant\Domain\Model\ValueObject\MessageContent;
use App\Assistant\Domain\Model\ValueObject\MessageId;
use App\Assistant\Domain\Model\ValueObject\MessagePayload;
use App\Assistant\Domain\Model\ValueObject\MessageRole;
use App\Assistant\Domain\Model\ValueObject\ToolCallRequest;
use App\Assistant\Domain\Port\AgentOutputStreamRegistry;
use App\Assistant\Domain\Port\Clock;
use App\Assistant\Domain\Port\IdGenerator;
use App\Assistant\Domain\Port\LlmPort;
use App\Assistant\Domain\Port\LlmReply;
use App\Assistant\Domain\Port\MessageRepository;
use App\Assistant\Domain\Port\SessionRepository;
use App\Assistant\Domain\Port\SystemPrompt;
use App\Assistant\Domain\Port\ToolGateway;

/**
 * Multi-turn agent loop:
 *   user message → LLM → (optional tool calls → execute → LLM) ... → final assistant text
 *
 * The loop is bounded by MAX_TURNS so a misbehaving LLM can never spin forever.
 * A doom-loop guard catches a tighter pathology: the same tool call repeated
 * verbatim DOOM_LOOP_THRESHOLD times in a row. When that triggers we drop the
 * advertised tool list for the next turn and inject a system nudge, forcing the
 * model to commit to a textual answer.
 */
final readonly class SendMessageHandler
{
    public const int MAX_TURNS = 10;
    public const int DOOM_LOOP_THRESHOLD = 3;

    public function __construct(
        private SessionRepository $sessions,
        private MessageRepository $messages,
        private LlmPort $llm,
        private ToolGateway $toolGateway,
        private IdGenerator $ids,
        private Clock $clock,
        private SystemPrompt $systemPrompt,
        private AgentOutputStreamRegistry $streams,
    ) {
    }

    public function __invoke(SendMessageCommand $command): SendMessageResult
    {
        if ('' === trim($command->userText)) {
            throw new InvalidArgument('User message cannot be empty.');
        }

        $session = $this->sessions->findById($command->sessionId);
        if (null === $session) {
            throw SessionNotFound::withId($command->sessionId);
        }

        $userMessage = $this->appendUserMessage($command, $session);

        $advertisements = $this->toolGateway->availableTools();
        $intermediate = [];
        $recentToolSignatures = [];
        $stream = $this->streams->current();

        for ($turn = 1; $turn <= self::MAX_TURNS; ++$turn) {
            $history = $this->messages->forSession($command->sessionId);
            // Prepend the foundational system prompt at call time (never persisted),
            // so it always leads the conversation without polluting the stored history.
            $conversation = array_merge([$this->systemPromptMessage($session)], $history);
            // Stream the assistant's text live to any attached UI as it arrives.
            $reply = $this->llm->completeStreaming(
                $session->model,
                $conversation,
                $advertisements,
                static function (string $delta) use ($stream): void {
                    $stream->assistantText($delta);
                },
            );

            if ([] === $reply->toolCalls) {
                $assistantMessage = $this->appendAssistantText($session, $reply->content);
                $session->touch($this->clock->now());
                $this->sessions->save($session);

                return new SendMessageResult(
                    $userMessage,
                    $assistantMessage,
                    $intermediate,
                    $reply->promptTokens,
                    $reply->completionTokens,
                );
            }

            // Persist the assistant turn that requested the tools, with the structured payload.
            $intermediate[] = $this->appendAssistantToolCallTurn($session, $reply);

            // Execute every requested tool and persist its result as a Tool-role message.
            foreach ($reply->toolCalls as $call) {
                $signature = $this->signature($call);
                array_unshift($recentToolSignatures, $signature);
                $recentToolSignatures = \array_slice($recentToolSignatures, 0, self::DOOM_LOOP_THRESHOLD);

                $stream->toolCall($call->name, $call->arguments);
                $result = $this->toolGateway->execute($call, $command->sessionId->value);
                $stream->toolResult($call->name, $result->output, $result->isError);
                $intermediate[] = $this->appendToolResultMessage($session, $call, $result->output, $result->isError);
            }

            // Doom-loop guard: if the model just emitted DOOM_LOOP_THRESHOLD identical
            // tool calls in a row, drop the tool list and add a system nudge so it
            // commits to a textual answer next turn.
            if ($this->isDoomLoop($recentToolSignatures)) {
                $advertisements = [];
                $intermediate[] = $this->appendSystemNudge($session);
            }
        }

        throw new AgentLoopExceeded(self::MAX_TURNS);
    }

    /**
     * Ephemeral System message carrying the foundational prompt. Built fresh
     * each turn and NOT persisted — it uses a sentinel id and never touches the
     * repository or the id generator.
     */
    private function systemPromptMessage(Session $session): Message
    {
        return new Message(
            MessageId::fromString('msg_system_prompt'),
            $session->id,
            MessageRole::System,
            MessageContent::of($this->systemPrompt->text()),
            $this->clock->now(),
        );
    }

    private function appendUserMessage(SendMessageCommand $command, Session $session): Message
    {
        $message = new Message(
            $this->ids->nextMessageId(),
            $command->sessionId,
            MessageRole::User,
            MessageContent::of($command->userText),
            $this->clock->now(),
        );
        $this->messages->append($message);

        return $message;
    }

    private function appendAssistantText(Session $session, string $content): Message
    {
        $message = new Message(
            $this->ids->nextMessageId(),
            $session->id,
            MessageRole::Assistant,
            MessageContent::of($content),
            $this->clock->now(),
        );
        $this->messages->append($message);

        return $message;
    }

    private function appendAssistantToolCallTurn(Session $session, LlmReply $reply): Message
    {
        $message = new Message(
            $this->ids->nextMessageId(),
            $session->id,
            MessageRole::Assistant,
            MessageContent::of($this->describeToolCalls($reply->toolCalls)),
            $this->clock->now(),
            MessagePayload::ofToolCalls($reply->toolCalls),
        );
        $this->messages->append($message);

        return $message;
    }

    private function appendToolResultMessage(
        Session $session,
        ToolCallRequest $call,
        string $output,
        bool $isError,
    ): Message {
        $marker = $isError ? '⚠' : '✓';
        $message = new Message(
            $this->ids->nextMessageId(),
            $session->id,
            MessageRole::Tool,
            MessageContent::of(\sprintf('%s %s → %s', $marker, $call->name, $this->summarize($output))),
            $this->clock->now(),
            MessagePayload::ofToolResult($call->id, $call->name, $output, $isError),
        );
        $this->messages->append($message);

        return $message;
    }

    private function appendSystemNudge(Session $session): Message
    {
        $message = new Message(
            $this->ids->nextMessageId(),
            $session->id,
            MessageRole::System,
            MessageContent::of(
                'You appear to be repeating the same tool call. '
                .'Stop calling tools and write a final textual answer with what you already have.',
            ),
            $this->clock->now(),
        );
        $this->messages->append($message);

        return $message;
    }

    /**
     * @param list<ToolCallRequest> $calls
     */
    private function describeToolCalls(array $calls): string
    {
        return implode(
            "\n",
            array_map(
                static fn (ToolCallRequest $c): string => \sprintf(
                    'tool_call %s(%s)',
                    $c->name,
                    json_encode($c->arguments, \JSON_UNESCAPED_SLASHES) ?: '{}',
                ),
                $calls,
            ),
        );
    }

    private function signature(ToolCallRequest $call): string
    {
        return $call->name.':'.(json_encode($call->arguments, \JSON_UNESCAPED_SLASHES) ?: '');
    }

    /**
     * @param list<string> $recent
     */
    private function isDoomLoop(array $recent): bool
    {
        if (\count($recent) < self::DOOM_LOOP_THRESHOLD) {
            return false;
        }

        return 1 === \count(array_unique($recent));
    }

    private function summarize(string $output): string
    {
        $oneLine = trim(preg_replace('/\s+/', ' ', $output) ?? '');

        return mb_strlen($oneLine) > 120 ? mb_substr($oneLine, 0, 117).'...' : $oneLine;
    }
}
