<?php

declare(strict_types=1);

namespace SebCode\Provider\Infrastructure\Ollama;

use SebCode\Provider\Domain\Model\ModelReply;
use SebCode\Provider\Domain\Model\ModelRequest;
use SebCode\Provider\Domain\ModelProviderInterface;
use SebCode\Provider\Domain\Port\LocalNetworkPolicy;

final readonly class OllamaProvider implements ModelProviderInterface
{
    public function __construct(
        private string $endpoint,
        private OllamaTransport $transport,
        private LocalNetworkPolicy $localNetworkPolicy,
        private MessageTranslator $messageTranslator = new MessageTranslator(),
        private ToolCallTranslator $toolCallTranslator = new ToolCallTranslator(),
        private int $timeoutSeconds = 60,
    ) {
    }

    public function name(): string
    {
        return 'ollama';
    }

    public function generate(ModelRequest $request): ModelReply
    {
        $url = rtrim($this->endpoint, '/').'/api/chat';
        $this->localNetworkPolicy->assertUrlAllowed($url);

        $response = $this->transport->postJson($url, [
            'model' => $request->model,
            'messages' => $this->messageTranslator->toOllamaMessages($request->messages),
            'stream' => false,
        ], $this->timeoutSeconds);

        $message = $response['message'] ?? [];
        if (!is_array($message)) {
            $message = [];
        }

        $content = $message['content'] ?? '';

        return new ModelReply(
            is_string($content) ? $content : '',
            $this->toolCallTranslator->fromOllamaToolCalls($message['tool_calls'] ?? []),
        );
    }
}
