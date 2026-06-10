<?php

declare(strict_types=1);

namespace SebCode\Provider\Infrastructure\Ollama;

use SebCode\Provider\Domain\Exception\ProviderUnavailable;

final class NativeOllamaTransport implements OllamaTransport
{
    public function postJson(string $url, array $payload, int $timeoutSeconds): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $body,
                'timeout' => $timeoutSeconds,
            ],
        ]);

        $response = file_get_contents($url, false, $context);
        if (false === $response) {
            throw new ProviderUnavailable(sprintf('Ollama request to "%s" failed.', $url));
        }

        $decoded = json_decode($response, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new ProviderUnavailable('Ollama returned an invalid JSON payload.');
        }

        $payload = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }
}
