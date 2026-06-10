<?php

declare(strict_types=1);

namespace SebCode\Provider\Infrastructure\Ollama;

use SebCode\Provider\Domain\Model\ToolCall;

final class ToolCallTranslator
{
    /**
     * @return list<ToolCall>
     */
    public function fromOllamaToolCalls(mixed $toolCalls): array
    {
        if (!is_array($toolCalls)) {
            return [];
        }

        $translated = [];
        foreach ($toolCalls as $toolCall) {
            if (!is_array($toolCall)) {
                continue;
            }

            $function = $toolCall['function'] ?? null;
            if (!is_array($function)) {
                continue;
            }

            $name = $function['name'] ?? null;
            if (!is_string($name) || '' === trim($name)) {
                continue;
            }

            $translated[] = new ToolCall($name, $this->arguments($function['arguments'] ?? []));
        }

        return $translated;
    }

    /**
     * @return array<string, mixed>
     */
    private function arguments(mixed $arguments): array
    {
        if (is_array($arguments)) {
            return $this->stringKeyArray($arguments);
        }

        if (is_string($arguments) && '' !== trim($arguments)) {
            $decoded = json_decode($arguments, true);
            if (is_array($decoded)) {
                return $this->stringKeyArray($decoded);
            }
        }

        return [];
    }

    /**
     * @param array<mixed, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function stringKeyArray(array $payload): array
    {
        $result = [];
        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
