<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\SubAgent;

use App\Assistant\Domain\Model\Message;
use App\Assistant\Domain\Model\ValueObject\MessageContent;
use App\Assistant\Domain\Model\ValueObject\MessageId;
use App\Assistant\Domain\Model\ValueObject\MessagePayload;
use App\Assistant\Domain\Model\ValueObject\MessageRole;
use App\Assistant\Domain\Model\ValueObject\ModelName;
use App\Assistant\Domain\Model\ValueObject\SessionId;
use App\Assistant\Domain\Model\ValueObject\ToolAdvertisement;
use App\Assistant\Domain\Model\ValueObject\ToolCallRequest;
use App\Assistant\Domain\Port\Clock;
use App\Assistant\Domain\Port\LlmPort;
use App\Assistant\Domain\Port\SystemPrompt;
use App\Assistant\Domain\Port\ToolGateway;
use App\Tool\Domain\Port\SubAgentRunner;

/**
 * Runs the `task` tool's nested agent loop, in memory and bounded.
 *
 * A trimmed, persistence-free version of the main agent loop: it advertises the
 * normal tools MINUS {@see self::EXCLUDED} (so a sub-agent cannot recurse via
 * `task` or touch the parent's `todowrite` list), runs up to {@see MAX_TURNS}
 * turns, executes tool calls through the same {@see ToolGateway} (scoped to the
 * parent session), and returns the final assistant text. Nothing is persisted.
 *
 * Implements a Tool-domain port from the Assistant context — the second
 * sanctioned cross-context bridge (ADR-0004).
 */
final readonly class AssistantSubAgentRunner implements SubAgentRunner
{
    public const int MAX_TURNS = 8;

    /** Tools a sub-agent never gets: `task` (recursion) and `todowrite` (parent state). */
    private const array EXCLUDED = ['task', 'todowrite'];

    public function __construct(
        private LlmPort $llm,
        private ToolGateway $toolGateway,
        private SystemPrompt $systemPrompt,
        private Clock $clock,
        private string $defaultModel,
    ) {
    }

    public function run(string $instruction, string $sessionId): string
    {
        $model = ModelName::of($this->defaultModel);
        $sid = SessionId::fromString('' === $sessionId ? 'ses_subagent' : $sessionId);
        $tools = array_values(array_filter(
            $this->toolGateway->availableTools(),
            static fn (ToolAdvertisement $t): bool => !\in_array($t->name, self::EXCLUDED, true),
        ));

        $conversation = [
            $this->message($sid, MessageRole::System, $this->systemPrompt->text()),
            $this->message($sid, MessageRole::User, $instruction),
        ];

        for ($turn = 1; $turn <= self::MAX_TURNS; ++$turn) {
            $reply = $this->llm->complete($model, $conversation, $tools);

            if ([] === $reply->toolCalls) {
                return $reply->content;
            }

            $conversation[] = $this->toolCallMessage($sid, $reply->toolCalls);
            foreach ($reply->toolCalls as $call) {
                if (\in_array($call->name, self::EXCLUDED, true)) {
                    $conversation[] = $this->toolResultMessage($sid, $call, \sprintf('tool "%s" is not available to a sub-agent', $call->name), true);
                    continue;
                }
                $result = $this->toolGateway->execute($call, $sessionId);
                $conversation[] = $this->toolResultMessage($sid, $call, $result->output, $result->isError);
            }
        }

        return '(sub-agent reached its turn limit without a final answer)';
    }

    private function message(SessionId $sid, MessageRole $role, string $text): Message
    {
        return new Message(MessageId::fromString('msg_subagent'), $sid, $role, MessageContent::of($text), $this->clock->now());
    }

    /**
     * @param list<ToolCallRequest> $calls
     */
    private function toolCallMessage(SessionId $sid, array $calls): Message
    {
        $names = implode(', ', array_map(static fn (ToolCallRequest $c): string => $c->name, $calls));

        return new Message(
            MessageId::fromString('msg_subagent'),
            $sid,
            MessageRole::Assistant,
            MessageContent::of('tool_calls: '.$names),
            $this->clock->now(),
            MessagePayload::ofToolCalls($calls),
        );
    }

    private function toolResultMessage(SessionId $sid, ToolCallRequest $call, string $output, bool $isError): Message
    {
        return new Message(
            MessageId::fromString('msg_subagent'),
            $sid,
            MessageRole::Tool,
            MessageContent::of(($isError ? '⚠ ' : '✓ ').$call->name),
            $this->clock->now(),
            MessagePayload::ofToolResult($call->id, $call->name, $output, $isError),
        );
    }
}
