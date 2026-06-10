<?php

declare(strict_types=1);

namespace SebCode\Tool\Application\Handler;

use SebCode\Tool\Application\Dto\Output\ToolResultOutput;
use SebCode\Tool\Application\UseCase\ExecuteToolUseCase;
use SebCode\Tool\Domain\Model\ToolExecutionContext;
use SebCode\Tool\Domain\Model\ToolResult;
use SebCode\Tool\Domain\Port\ToolRepository;

final readonly class ExecuteToolHandler
{
    public function __construct(private ToolRepository $toolRepository)
    {
    }

    public function __invoke(ExecuteToolUseCase $useCase): ToolResultOutput
    {
        try {
            $tool = $this->toolRepository->get($useCase->input->toolName);

            return ToolResultOutput::fromDomain($tool->execute(
                new ToolExecutionContext($useCase->input->workspaceRoot),
                $useCase->input->input,
            ));
        } catch (\Throwable $throwable) {
            return ToolResultOutput::fromDomain(ToolResult::failure($throwable->getMessage(), [
                'tool' => $useCase->input->toolName,
                'error' => $throwable::class,
            ]));
        }
    }
}
