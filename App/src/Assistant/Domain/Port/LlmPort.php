<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Port;

use App\Assistant\Domain\Exception\LlmUnavailable;
use App\Assistant\Domain\Model\Message;
use App\Assistant\Domain\Model\ValueObject\ModelName;

interface LlmPort
{
    /**
     * @param list<Message> $conversation in chronological order
     *
     * @throws LlmUnavailable
     */
    public function complete(ModelName $model, array $conversation): LlmReply;
}
