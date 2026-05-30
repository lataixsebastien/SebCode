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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

#[CoversClass(SymfonyAiOllamaAdapter::class)]
final class SymfonyAiOllamaAdapterTest extends TestCase
{
    public function testTranslatesDomainRolesIntoMessageBagAndReturnsTextResultAsLlmReply(): void
    {
        $platform = $this->recordingPlatform(new TextResult('hello back'));
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
        $platform = $this->recordingPlatform(
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
        $adapter = new SymfonyAiOllamaAdapter($this->recordingPlatform(new TextResult('ignored')));

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

    private function recordingPlatform(
        ResultInterface $stubResult,
        ?TokenUsageInterface $tokenUsage = null,
    ): object {
        return new class($stubResult, $tokenUsage) implements PlatformInterface {
            public ?string $lastModel = null;
            public mixed $lastInput = null;

            public function __construct(
                private readonly ResultInterface $stubResult,
                private readonly ?TokenUsageInterface $tokenUsage,
            ) {
            }

            public function invoke(string $model, array|string|object $input, array $options = []): DeferredResult
            {
                $this->lastModel = $model;
                $this->lastInput = $input;

                $converter = new class($this->stubResult, $this->tokenUsage) implements ResultConverterInterface {
                    public function __construct(
                        private readonly ResultInterface $stub,
                        private readonly ?TokenUsageInterface $tokenUsage,
                    ) {
                    }

                    public function supports(Model $model): bool
                    {
                        return true;
                    }

                    public function convert($rawResult, array $options = []): ResultInterface
                    {
                        return $this->stub;
                    }

                    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
                    {
                        if (null === $this->tokenUsage) {
                            return null;
                        }

                        $usage = $this->tokenUsage;

                        return new class($usage) implements TokenUsageExtractorInterface {
                            public function __construct(private readonly TokenUsageInterface $usage)
                            {
                            }

                            public function extract($rawResult, array $options = []): ?TokenUsageInterface
                            {
                                return $this->usage;
                            }
                        };
                    }
                };

                return new DeferredResult($converter, new InMemoryRawResult());
            }

            public function getModelCatalog(): ModelCatalogInterface
            {
                throw new \LogicException('not used');
            }
        };
    }
}
