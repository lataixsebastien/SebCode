<?php

declare(strict_types=1);

namespace App\Tests\Support\Assistant\Doubles;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

/**
 * Test double for PlatformInterface.
 *
 * Records each invoke() call and returns a real DeferredResult built from a
 * caller-provided stub ResultInterface (optionally with TokenUsage in the
 * result metadata).
 *
 * Behaviour matches what SymfonyAiOllamaAdapter expects from the AI platform.
 */
final class RecordingPlatform implements PlatformInterface
{
    public ?string $lastModel = null;
    public mixed $lastInput = null;

    public function __construct(
        private readonly ResultInterface $stubResult,
        private readonly ?TokenUsageInterface $tokenUsage = null,
    ) {
    }

    public function invoke(string $model, array|string|object $input, array $options = []): DeferredResult
    {
        $this->lastModel = $model;
        $this->lastInput = $input;

        return new DeferredResult($this->buildConverter(), new InMemoryRawResult());
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        throw new \LogicException('RecordingPlatform: getModelCatalog() is not used in tests.');
    }

    private function buildConverter(): ResultConverterInterface
    {
        return new class($this->stubResult, $this->tokenUsage) implements ResultConverterInterface {
            public function __construct(
                private readonly ResultInterface $stub,
                private readonly ?TokenUsageInterface $tokenUsage,
            ) {
            }

            public function supports(Model $model): bool
            {
                return true;
            }

            public function convert(RawResultInterface $result, array $options = []): ResultInterface
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

                    public function extract(RawResultInterface $rawResult, array $options = []): TokenUsageInterface
                    {
                        return $this->usage;
                    }
                };
            }
        };
    }
}
