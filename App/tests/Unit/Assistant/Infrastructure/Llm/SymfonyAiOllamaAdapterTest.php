<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\Infrastructure\Llm;

use App\Assistant\Domain\Exception\LlmUnavailable;
use App\Assistant\Domain\Model\Message;
use App\Assistant\Domain\Model\ValueObject\MessageContent;
use App\Assistant\Domain\Model\ValueObject\MessageId;
use App\Assistant\Domain\Model\ValueObject\MessageRole;
use App\Assistant\Domain\Model\ValueObject\ModelName;
use App\Assistant\Domain\Model\ValueObject\SessionId;
use App\Assistant\Domain\Model\ValueObject\ToolAdvertisement;
use App\Assistant\Domain\Model\ValueObject\ToolCallRequest;
use App\Assistant\Infrastructure\Llm\SymfonyAiOllamaAdapter;
use App\Tests\Support\Assistant\Doubles\RecordingPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;

#[CoversClass(SymfonyAiOllamaAdapter::class)]
final class SymfonyAiOllamaAdapterTest extends TestCase
{
    public function testTranslatesDomainRolesIntoMessageBagAndReturnsTextResultAsLlmReply(): void
    {
        $platform = new RecordingPlatform(new TextResult('hello back'));
        $adapter = new SymfonyAiOllamaAdapter($platform);

        $reply = $adapter->complete(
            ModelName::of('qwen2.5:3b'),
            [
                $this->msg(MessageRole::System, 'You are concise.'),
                $this->msg(MessageRole::User, 'Hi'),
                $this->msg(MessageRole::Assistant, 'Hello.'),
                $this->msg(MessageRole::User, 'Continue.'),
            ],
        );

        self::assertSame('hello back', $reply->content);
        self::assertNull($reply->promptTokens);
        self::assertNull($reply->completionTokens);
        self::assertSame([], $reply->toolCalls);

        self::assertSame('qwen2.5:3b', $platform->lastModel);
        self::assertInstanceOf(MessageBag::class, $platform->lastInput);
        self::assertArrayNotHasKey('tools', $platform->lastOptions);

        $iter = iterator_to_array($platform->lastInput);
        self::assertInstanceOf(SystemMessage::class, $iter[0]);
        self::assertInstanceOf(UserMessage::class, $iter[1]);
        self::assertInstanceOf(AssistantMessage::class, $iter[2]);
        self::assertInstanceOf(UserMessage::class, $iter[3]);
    }

    public function testExtractsTokenUsageWhenPresentInMetadata(): void
    {
        $platform = new RecordingPlatform(
            new TextResult('ok'),
            new TokenUsage(promptTokens: 17, completionTokens: 5),
        );
        $adapter = new SymfonyAiOllamaAdapter($platform);

        $reply = $adapter->complete(
            ModelName::of('qwen2.5:3b'),
            [$this->msg(MessageRole::User, 'hi')],
        );

        self::assertSame('ok', $reply->content);
        self::assertSame(17, $reply->promptTokens);
        self::assertSame(5, $reply->completionTokens);
    }

    public function testWrapsAnyPlatformExceptionInLlmUnavailable(): void
    {
        $platform = new class implements PlatformInterface {
            public function invoke(string $model, array|string|object $input, array $options = []): DeferredResult
            {
                throw new \RuntimeException('Connection refused');
            }

            public function getModelCatalog(): ModelCatalogInterface
            {
                throw new \LogicException('not used');
            }
        };
        $adapter = new SymfonyAiOllamaAdapter($platform);

        $this->expectException(LlmUnavailable::class);
        $this->expectExceptionMessageMatches('/Connection refused/');

        $adapter->complete(
            ModelName::of('qwen2.5:3b'),
            [$this->msg(MessageRole::User, 'hi')],
        );
    }

    public function testRejectsToolRoleMessageWithoutPayload(): void
    {
        $platform = new RecordingPlatform(new TextResult('ignored'));
        $adapter = new SymfonyAiOllamaAdapter($platform);

        $this->expectException(LlmUnavailable::class);
        $this->expectExceptionMessageMatches('/missing a tool-result payload/');

        // A Tool-role message without a MessagePayload is an invalid state we
        // refuse to silently swallow — the SendMessageHandler always attaches
        // a payload when it persists Tool messages (see STEP-12).
        $adapter->complete(
            ModelName::of('qwen2.5:3b'),
            [$this->msg(MessageRole::Tool, 'some-tool-result')],
        );
    }

    public function testAdvertisesToolsToThePlatformWhenProvided(): void
    {
        $platform = new RecordingPlatform(new TextResult('ok'));
        $adapter = new SymfonyAiOllamaAdapter($platform);

        $adapter->complete(
            ModelName::of('qwen2.5:3b'),
            [$this->msg(MessageRole::User, 'list files')],
            [
                new ToolAdvertisement(
                    name: 'glob',
                    description: 'Find files by glob pattern.',
                    parameters: ['type' => 'object', 'properties' => ['pattern' => ['type' => 'string']], 'required' => ['pattern']],
                ),
            ],
        );

        self::assertArrayHasKey('tools', $platform->lastOptions);
        self::assertSame([[
            'type' => 'function',
            'function' => [
                'name' => 'glob',
                'description' => 'Find files by glob pattern.',
                'parameters' => ['type' => 'object', 'properties' => ['pattern' => ['type' => 'string']], 'required' => ['pattern']],
            ],
        ]], $platform->lastOptions['tools']);
    }

    public function testParsesToolCallResultIntoLlmReplyToolCalls(): void
    {
        $platform = new RecordingPlatform(
            new ToolCallResult([
                new ToolCall('tcl_abc', 'glob', ['pattern' => '**/*.php', 'limit' => 50]),
                new ToolCall('tcl_def', 'read', ['filePath' => 'README.md']),
            ]),
            new TokenUsage(promptTokens: 22, completionTokens: 9),
        );
        $adapter = new SymfonyAiOllamaAdapter($platform);

        $reply = $adapter->complete(
            ModelName::of('qwen2.5:3b'),
            [$this->msg(MessageRole::User, 'go')],
            [
                new ToolAdvertisement('glob', 'desc', ['type' => 'object']),
                new ToolAdvertisement('read', 'desc', ['type' => 'object']),
            ],
        );

        self::assertSame('', $reply->content);
        self::assertSame(22, $reply->promptTokens);
        self::assertSame(9, $reply->completionTokens);
        self::assertCount(2, $reply->toolCalls);

        self::assertInstanceOf(ToolCallRequest::class, $reply->toolCalls[0]);
        self::assertSame('tcl_abc', $reply->toolCalls[0]->id);
        self::assertSame('glob', $reply->toolCalls[0]->name);
        self::assertSame(['pattern' => '**/*.php', 'limit' => 50], $reply->toolCalls[0]->arguments);

        self::assertSame('tcl_def', $reply->toolCalls[1]->id);
        self::assertSame('read', $reply->toolCalls[1]->name);
    }

    public function testTextResultStillWorksWithToolsAdvertisedButNoneInvoked(): void
    {
        $platform = new RecordingPlatform(new TextResult('I do not need tools to answer.'));
        $adapter = new SymfonyAiOllamaAdapter($platform);

        $reply = $adapter->complete(
            ModelName::of('qwen2.5:3b'),
            [$this->msg(MessageRole::User, 'hi')],
            [new ToolAdvertisement('glob', 'desc', ['type' => 'object'])],
        );

        self::assertSame('I do not need tools to answer.', $reply->content);
        self::assertSame([], $reply->toolCalls);
    }

    private function msg(MessageRole $role, string $text): Message
    {
        return new Message(
            MessageId::fromString('msg_'.bin2hex(random_bytes(4))),
            SessionId::fromString('ses_test'),
            $role,
            MessageContent::of($text),
            new \DateTimeImmutable(),
        );
    }
}
