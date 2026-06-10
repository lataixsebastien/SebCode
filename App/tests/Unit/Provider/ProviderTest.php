<?php

declare(strict_types=1);

namespace SebCode\Tests\Unit\Provider;

use PHPUnit\Framework\TestCase;
use SebCode\Provider\Application\Dto\Input\GenerateModelReplyInput;
use SebCode\Provider\Application\Dto\Input\ModelMessageInput;
use SebCode\Provider\Application\Handler\GenerateModelReplyHandler;
use SebCode\Provider\Application\UseCase\GenerateModelReplyUseCase;
use SebCode\Provider\Domain\Exception\ProviderNotFound;
use SebCode\Provider\Domain\Model\ModelReply;
use SebCode\Provider\Domain\Model\ModelRequest;
use SebCode\Provider\Domain\ModelProviderInterface;
use SebCode\Provider\Domain\Port\LocalNetworkPolicy;
use SebCode\Provider\Domain\ProviderRegistry;
use SebCode\Provider\Infrastructure\Ollama\MessageTranslator;
use SebCode\Provider\Infrastructure\Ollama\OllamaProvider;
use SebCode\Provider\Infrastructure\Ollama\OllamaTransport;
use SebCode\Provider\Infrastructure\Ollama\ToolCallTranslator;
use SebCode\Provider\Infrastructure\Security\SecurityLocalNetworkPolicyAdapter;
use SebCode\Security\Domain\Exception\SecurityViolation;
use SebCode\Security\Domain\NetworkPolicy;

final class ProviderTest extends TestCase
{
    public function testRegistryReturnsProvidersByName(): void
    {
        $registry = new ProviderRegistry();
        $registry->register(new FakeProvider('secondary', 'two'));
        $registry->register(new FakeProvider('primary', 'one'));

        self::assertSame(['primary', 'secondary'], $registry->names());
        self::assertSame('primary', $registry->get('primary')->name());
    }

    public function testRegistryThrowsForMissingProvider(): void
    {
        $registry = new ProviderRegistry();

        $this->expectException(ProviderNotFound::class);

        $registry->get('missing');
    }

    public function testHandlerUsesSelectedProvider(): void
    {
        $registry = new ProviderRegistry();
        $registry->register(new FakeProvider('ollama', 'from ollama'));
        $handler = new GenerateModelReplyHandler($registry);

        $reply = $handler(new GenerateModelReplyUseCase(new GenerateModelReplyInput(
            'ollama',
            'qwen2.5:3b',
            [new ModelMessageInput('user', 'hello')],
        )));

        self::assertSame('from ollama', $reply->content);
    }

    public function testOllamaProviderTranslatesRequestAndResponse(): void
    {
        $transport = new RecordingOllamaTransport([
            'message' => [
                'content' => 'hello human',
                'tool_calls' => [
                    [
                        'function' => [
                            'name' => 'read_file',
                            'arguments' => '{"path":"src/Foo.php"}',
                        ],
                    ],
                ],
            ],
        ]);
        $provider = new OllamaProvider(
            'http://127.0.0.1:11434',
            $transport,
            new AllowAllLocalNetworkPolicy(),
            new MessageTranslator(),
            new ToolCallTranslator(),
            12,
        );

        $reply = $provider->generate(new ModelRequest('qwen2.5:3b', [
            new \SebCode\Provider\Domain\Model\ModelMessage('user', 'hello'),
        ]));

        self::assertSame('ollama', $provider->name());
        self::assertSame('http://127.0.0.1:11434/api/chat', $transport->lastUrl);
        self::assertSame(12, $transport->lastTimeout);
        self::assertSame('qwen2.5:3b', $transport->lastPayload['model']);
        self::assertSame(false, $transport->lastPayload['stream']);
        self::assertSame([['role' => 'user', 'content' => 'hello']], $transport->lastPayload['messages']);
        self::assertSame('hello human', $reply->content);
        self::assertCount(1, $reply->toolCalls);
        self::assertSame('read_file', $reply->toolCalls[0]->name);
        self::assertSame('src/Foo.php', $reply->toolCalls[0]->arguments['path']);
    }

    public function testOllamaProviderRejectsExternalEndpoint(): void
    {
        $provider = new OllamaProvider(
            'https://example.com',
            new RecordingOllamaTransport(['message' => ['content' => 'never']]),
            new SecurityLocalNetworkPolicyAdapter(new NetworkPolicy()),
        );

        $this->expectException(SecurityViolation::class);

        $provider->generate(new ModelRequest('qwen2.5:3b', [
            new \SebCode\Provider\Domain\Model\ModelMessage('user', 'hello'),
        ]));
    }
}

final readonly class FakeProvider implements ModelProviderInterface
{
    public function __construct(private string $name, private string $content)
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function generate(ModelRequest $request): ModelReply
    {
        return new ModelReply($this->content);
    }
}

final class RecordingOllamaTransport implements OllamaTransport
{
    public ?string $lastUrl = null;

    /**
     * @var array<string, mixed>
     */
    public array $lastPayload = [];

    public ?int $lastTimeout = null;

    /**
     * @param array<string, mixed> $response
     */
    public function __construct(private readonly array $response)
    {
    }

    public function postJson(string $url, array $payload, int $timeoutSeconds): array
    {
        $this->lastUrl = $url;
        $this->lastPayload = $payload;
        $this->lastTimeout = $timeoutSeconds;

        return $this->response;
    }
}

final class AllowAllLocalNetworkPolicy implements LocalNetworkPolicy
{
    public function assertUrlAllowed(string $url): void
    {
    }
}
