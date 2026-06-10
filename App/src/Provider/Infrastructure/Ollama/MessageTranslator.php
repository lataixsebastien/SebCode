<?php

declare(strict_types=1);

namespace SebCode\Provider\Infrastructure\Ollama;

use SebCode\Provider\Domain\Model\ModelMessage;

final class MessageTranslator
{
    /**
     * @param list<ModelMessage> $messages
     *
     * @return list<array{role: string, content: string}>
     */
    public function toOllamaMessages(array $messages): array
    {
        return array_map(
            static fn (ModelMessage $message): array => [
                'role' => $message->role,
                'content' => $message->content,
            ],
            $messages,
        );
    }
}
