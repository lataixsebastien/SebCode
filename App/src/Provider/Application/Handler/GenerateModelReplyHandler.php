<?php

declare(strict_types=1);

namespace SebCode\Provider\Application\Handler;

use SebCode\Provider\Application\Dto\Input\ModelMessageInput;
use SebCode\Provider\Application\Dto\Output\ModelReplyOutput;
use SebCode\Provider\Application\UseCase\GenerateModelReplyUseCase;
use SebCode\Provider\Domain\Model\ModelRequest;
use SebCode\Provider\Domain\ProviderRegistry;

final readonly class GenerateModelReplyHandler
{
    public function __construct(private ProviderRegistry $providerRegistry)
    {
    }

    public function __invoke(GenerateModelReplyUseCase $useCase): ModelReplyOutput
    {
        return ModelReplyOutput::fromDomain($this->providerRegistry
            ->get($useCase->input->providerName)
            ->generate(new ModelRequest(
                $useCase->input->model,
                array_map(
                    static fn (ModelMessageInput $message): \SebCode\Provider\Domain\Model\ModelMessage => $message->toDomain(),
                    $useCase->input->messages,
                ),
            )));
    }
}
