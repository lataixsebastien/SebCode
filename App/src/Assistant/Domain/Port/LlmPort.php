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
}
