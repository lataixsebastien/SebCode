<?php

declare(strict_types=1);

namespace App\Tests\Support\Assistant\Doubles;

use App\Assistant\Domain\Exception\LlmUnavailable;
use App\Assistant\Domain\Model\Message;
use App\Assistant\Domain\Model\ValueObject\ModelName;
use App\Assistant\Domain\Port\LlmPort;
use App\Assistant\Domain\Port\LlmReply;

/**
 * LLM double: returns canned replies in sequence and records every call.
 */
final class ScriptedLlm implements LlmPort
{
    /** @var list<LlmReply> */
    private array $replies = [];

    /** @var list<array{model: ModelName, conversation: list<Message>}> */
    private array $calls = [];

    private ?\Throwable $throwInstead = null;

    public function scriptReply(string $content, ?int $promptTokens = null, ?int $completionTokens = null): void
    {
        $this->replies[] = new LlmReply($content, $promptTokens, $completionTokens);
    }

    public function scriptFailure(\Throwable $error): void
    {
        $this->throwInstead = $error;
    }

    public function complete(ModelName $model, array $conversation): LlmReply
    {
        $this->calls[] = ['model' => $model, 'conversation' => $conversation];

        if (null !== $this->throwInstead) {
            $error = $this->throwInstead;
            $this->throwInstead = null;
            throw $error;
        }

        if ([] === $this->replies) {
            throw LlmUnavailable::fromUpstream('ScriptedLlm has no more replies queued');
        }

        return array_shift($this->replies);
    }

    /**
     * @return list<array{model: ModelName, conversation: list<Message>}>
     */
    public function calls(): array
    {
        return $this->calls;
    }
}
