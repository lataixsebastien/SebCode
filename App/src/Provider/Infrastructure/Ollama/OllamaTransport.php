<?php

declare(strict_types=1);

namespace SebCode\Provider\Infrastructure\Ollama;

interface OllamaTransport
{
    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function postJson(string $url, array $payload, int $timeoutSeconds): array;
}
