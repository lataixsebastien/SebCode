<?php

declare(strict_types=1);

namespace SebCode\Tool\Application\Handler;

use SebCode\Tool\Application\Dto\Output\ToolDescriptorOutput;
use SebCode\Tool\Application\UseCase\ListToolsUseCase;
use SebCode\Tool\Domain\Model\ToolDescriptor;
use SebCode\Tool\Domain\Port\ToolRepository;

final readonly class ListToolsHandler
{
    public function __construct(private ToolRepository $toolRepository)
    {
    }

    /**
     * @return list<ToolDescriptorOutput>
     */
    public function __invoke(ListToolsUseCase $useCase): array
    {
        $descriptors = null === $useCase->mode
            ? $this->toolRepository->descriptors()
            : $this->toolRepository->availableDescriptors($useCase->mode);

        return array_map(
            static fn (ToolDescriptor $descriptor): ToolDescriptorOutput => ToolDescriptorOutput::fromDomain($descriptor),
            $descriptors,
        );
    }
}
