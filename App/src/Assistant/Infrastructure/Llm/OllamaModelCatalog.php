<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Llm;

use App\Assistant\Domain\Model\ValueObject\ModelName;
use App\Assistant\Domain\Port\ModelCatalog;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * ModelCatalog adapter backed by the local Ollama HTTP API (`GET /api/tags`).
 *
 * Failures are swallowed into an empty list: the picker shows "no models"
 * rather than crashing the TUI when Ollama is down.
 */
final readonly class OllamaModelCatalog implements ModelCatalog
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $endpoint,
    ) {
    }

    public function availableModels(): array
    {
        try {
            $response = $this->httpClient->request('GET', rtrim($this->endpoint, '/').'/api/tags', [
                'timeout' => 5,
            ]);
            $payload = $response->toArray();
        } catch (\Throwable) {
            return [];
        }

        $entries = $payload['models'] ?? [];
        if (!\is_array($entries)) {
            return [];
        }

        $models = [];
        foreach ($entries as $entry) {
            $name = \is_array($entry) ? ($entry['name'] ?? null) : null;
            if (\is_string($name) && '' !== $name) {
                $models[] = ModelName::of($name);
            }
        }

        usort($models, static fn (ModelName $a, ModelName $b): int => strcmp($a->value, $b->value));

        return $models;
    }
}
