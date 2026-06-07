<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Llm;

use App\Assistant\Domain\Exception\LlmUnavailable;
use App\Assistant\Domain\Model\Message;
use App\Assistant\Domain\Model\ValueObject\MessageRole;
use App\Assistant\Domain\Model\ValueObject\ModelName;
use App\Assistant\Domain\Model\ValueObject\ToolAdvertisement;
use App\Assistant\Domain\Model\ValueObject\ToolCallRequest;
use App\Assistant\Domain\Port\LlmPort;
use App\Assistant\Domain\Port\LlmReply;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

/**
 * LlmPort adapter backed by symfony/ai-platform.
 *
 * Configured to talk to a local or remote Ollama endpoint via the
 * symfony/ai-bundle service "ai.platform.ollama".
 */
final readonly class SymfonyAiOllamaAdapter implements LlmPort
{
    public function __construct(private PlatformInterface $platform)
    {
    }

    public function complete(ModelName $model, array $conversation, array $tools = []): LlmReply
    {
        $bag = $this->toMessageBag($conversation);
        $options = $this->toolOptions($tools);

        try {
            $deferred = $this->platform->invoke($model->value, $bag, $options);
            $result = $deferred->getResult();
        } catch (\Throwable $e) {
            throw LlmUnavailable::fromUpstream($e->getMessage(), $e);
        }

        if ($result instanceof ToolCallResult) {
            return new LlmReply(
                content: '',
                promptTokens: $this->tokenUsageFrom($result)?->getPromptTokens(),
                completionTokens: $this->tokenUsageFrom($result)?->getCompletionTokens(),
                toolCalls: array_values(array_map(
                    static fn (ToolCall $tc): ToolCallRequest => new ToolCallRequest(
                        id: $tc->getId(),
                        name: $tc->getName(),
                        arguments: $tc->getArguments(),
                    ),
                    $result->getContent(),
                )),
            );
        }

        if (!$result instanceof TextResult) {
            throw LlmUnavailable::fromUpstream(\sprintf('Unexpected result type %s returned by LLM platform; expected TextResult or ToolCallResult.', $result::class));
        }

        $tokenUsage = $this->tokenUsageFrom($result);

        return new LlmReply(
            content: $result->getContent(),
            promptTokens: $tokenUsage?->getPromptTokens(),
            completionTokens: $tokenUsage?->getCompletionTokens(),
        );
    }

    public function completeStreaming(ModelName $model, array $conversation, array $tools, callable $onText, ?callable $shouldStop = null): LlmReply
    {
        $bag = $this->toMessageBag($conversation);
        $options = $this->toolOptions($tools);
        $options['stream'] = true;

        $content = '';
        $toolCalls = [];
        $promptTokens = null;
        $completionTokens = null;

        try {
            $deferred = $this->platform->invoke($model->value, $bag, $options);
            foreach ($deferred->asStream() as $delta) {
                if (null !== $shouldStop && $shouldStop()) {
                    break;
                }
                if ($delta instanceof TextDelta) {
                    $content .= $delta->getText();
                    $onText($delta->getText());
                } elseif ($delta instanceof ToolCallComplete) {
                    foreach ($delta->getToolCalls() as $tc) {
                        $toolCalls[] = new ToolCallRequest($tc->getId(), $tc->getName(), $tc->getArguments());
                    }
                }
            }

            // The platform's TokenUsage StreamListener lifts the trailing usage
            // delta OUT of the stream (skipDelta) and into result metadata, so it
            // never arrives as a delta above — read it from metadata once the
            // stream is fully drained (asStream() copies it across on completion).
            $usage = $deferred->getMetadata()->get('token_usage');
            if ($usage instanceof TokenUsageInterface) {
                $promptTokens = $usage->getPromptTokens();
                $completionTokens = $usage->getCompletionTokens();
            }
        } catch (\Throwable $e) {
            throw LlmUnavailable::fromUpstream($e->getMessage(), $e);
        }

        return new LlmReply($content, $promptTokens, $completionTokens, $toolCalls);
    }

    /**
     * @param list<ToolAdvertisement> $tools
     *
     * @return array<string, mixed>
     */
    private function toolOptions(array $tools): array
    {
        if ([] === $tools) {
            return [];
        }

        // OpenAI-style function-calling shape; the OllamaClient lifts "tools"
        // to a top-level JSON field on /api/chat.
        return [
            'tools' => array_map(
                static fn (ToolAdvertisement $t): array => [
                    'type' => 'function',
                    'function' => [
                        'name' => $t->name,
                        'description' => $t->description,
                        'parameters' => $t->parameters,
                    ],
                ],
                $tools,
            ),
        ];
    }

    private function tokenUsageFrom(TextResult|ToolCallResult $result): ?TokenUsageInterface
    {
        $usage = $result->getMetadata()->get('token_usage');

        return $usage instanceof TokenUsageInterface ? $usage : null;
    }

    /**
     * @param list<Message> $conversation
     */
    private function toMessageBag(array $conversation): MessageBag
    {
        $bag = new MessageBag();
        foreach ($conversation as $message) {
            $bag->add(match ($message->role) {
                MessageRole::System => new SystemMessage($message->content->text),
                MessageRole::User => new UserMessage(new Text($message->content->text)),
                MessageRole::Assistant => new AssistantMessage(new Text($message->content->text)),
                MessageRole::Tool => $this->toToolCallMessage($message),
            });
        }

        return $bag;
    }

    private function toToolCallMessage(Message $message): ToolCallMessage
    {
        $payload = $message->payload;
        if (null === $payload || null === $payload->toolCallId || null === $payload->toolName) {
            throw LlmUnavailable::fromUpstream('Tool role message is missing a tool-result payload; cannot translate to the LLM contract.');
        }

        return new ToolCallMessage(
            new ToolCall($payload->toolCallId, $payload->toolName),
            $payload->toolOutput ?? '',
        );
    }
}
