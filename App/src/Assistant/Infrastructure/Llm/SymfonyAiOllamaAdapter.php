<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Llm;

use App\Assistant\Domain\Exception\LlmUnavailable;
use App\Assistant\Domain\Model\Message;
use App\Assistant\Domain\Model\ValueObject\MessageRole;
use App\Assistant\Domain\Model\ValueObject\ModelName;
use App\Assistant\Domain\Port\LlmPort;
use App\Assistant\Domain\Port\LlmReply;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\TextResult;
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

    public function complete(ModelName $model, array $conversation): LlmReply
    {
        $bag = $this->toMessageBag($conversation);

        try {
            $deferred = $this->platform->invoke($model->value, $bag);
            $result = $deferred->getResult();
        } catch (\Throwable $e) {
            throw LlmUnavailable::fromUpstream($e->getMessage(), $e);
        }

        if (!$result instanceof TextResult) {
            throw LlmUnavailable::fromUpstream(\sprintf('Unexpected result type %s returned by LLM platform; expected TextResult.', $result::class));
        }

        $promptTokens = null;
        $completionTokens = null;
        $tokenUsage = $result->getMetadata()->get('token_usage');
        if ($tokenUsage instanceof TokenUsageInterface) {
            $promptTokens = $tokenUsage->getPromptTokens();
            $completionTokens = $tokenUsage->getCompletionTokens();
        }

        return new LlmReply($result->getContent(), $promptTokens, $completionTokens);
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
                MessageRole::Tool => throw LlmUnavailable::fromUpstream('Tool messages are not yet supported in the conversation history.'),
            });
        }

        return $bag;
    }
}
