<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Port;

use App\Assistant\Domain\Exception\LlmUnavailable;
use App\Assistant\Domain\Model\Message;
use App\Assistant\Domain\Model\ValueObject\ModelName;
use App\Assistant\Domain\Model\ValueObject\ToolAdvertisement;

interface LlmPort
{
    /**
     * @param list<Message> $conversation in chronological order
     * @param list<ToolAdvertisement> $tools advertised to the LLM; empty disables tool calling
     *
     * @throws LlmUnavailable
     */
    public function complete(ModelName $model, array $conversation, array $tools = []): LlmReply;

    /**
     * Like {@see complete()} but streams the assistant's text: `$onText` is
     * called with each chunk as it arrives. The returned reply still carries
     * the full content, tool calls and token usage.
     *
     * @param list<Message> $conversation
     * @param list<ToolAdvertisement> $tools
     * @param callable(string): void $onText
     *
     * @throws LlmUnavailable
     */
    public function completeStreaming(ModelName $model, array $conversation, array $tools, callable $onText): LlmReply;
}
