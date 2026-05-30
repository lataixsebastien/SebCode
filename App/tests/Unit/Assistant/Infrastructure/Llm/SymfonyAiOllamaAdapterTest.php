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

        self::assertSame('qwen2.5:3b', $platform->lastModel);
        self::assertInstanceOf(MessageBag::class, $platform->lastInput);

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

    public function testRejectsToolRoleUntilToolsAreSupported(): void
    {
        $platform = new RecordingPlatform(new TextResult('ignored'));
        $adapter = new SymfonyAiOllamaAdapter($platform);

        $this->expectException(LlmUnavailable::class);
        $this->expectExceptionMessageMatches('/Tool messages/');

        $adapter->complete(
            ModelName::of('qwen2.5:3b'),
            [$this->msg(MessageRole::Tool, 'some-tool-result')],
        );
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
