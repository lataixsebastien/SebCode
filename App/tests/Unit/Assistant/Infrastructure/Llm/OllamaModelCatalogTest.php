<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\Infrastructure\Llm;

use App\Assistant\Infrastructure\Llm\OllamaModelCatalog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(OllamaModelCatalog::class)]
final class OllamaModelCatalogTest extends TestCase
{
    public function testListsAndSortsModelsFromOllamaTags(): void
    {
        $client = new MockHttpClient(static function (string $method, string $url): MockResponse {
            self::assertSame('GET', $method);
            self::assertSame('http://ollama.local:11434/api/tags', $url);

            return new MockResponse((string) json_encode([
                'models' => [
                    ['name' => 'qwen2.5:7b'],
                    ['name' => 'llama3.2:3b'],
                    ['name' => 'qwen2.5:3b'],
                ],
            ]));
        });

        $catalog = new OllamaModelCatalog($client, 'http://ollama.local:11434/');

        $names = array_map(static fn ($m): string => $m->value, $catalog->availableModels());

        self::assertSame(['llama3.2:3b', 'qwen2.5:3b', 'qwen2.5:7b'], $names);
    }

    public function testSkipsMalformedEntries(): void
    {
        $client = new MockHttpClient(new MockResponse((string) json_encode([
            'models' => [
                ['name' => 'qwen2.5:3b'],
                ['name' => ''],
                ['size' => 123],
            ],
        ])));

        $catalog = new OllamaModelCatalog($client, 'http://ollama.local:11434');

        self::assertCount(1, $catalog->availableModels());
    }

    public function testUnreachableOllamaYieldsEmptyList(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['error' => 'connection refused']));

        $catalog = new OllamaModelCatalog($client, 'http://ollama.local:11434');

        self::assertSame([], $catalog->availableModels());
    }
}
