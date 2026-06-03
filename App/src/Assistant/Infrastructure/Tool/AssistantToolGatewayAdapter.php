<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Tool;

use App\Assistant\Domain\Model\ValueObject\ToolAdvertisement;
use App\Assistant\Domain\Model\ValueObject\ToolCallRequest;
use App\Assistant\Domain\Model\ValueObject\ToolResultDto;
use App\Assistant\Domain\Port\Clock;
use App\Assistant\Domain\Port\ToolGateway;
use App\Tool\Application\Command\ExecuteToolCommand;
use App\Tool\Application\Command\ExecuteToolHandler;
use App\Tool\Application\Query\ListToolsHandler;
use App\Tool\Application\Query\ListToolsQuery;
use App\Tool\Domain\Exception\ToolNotFound;
use App\Tool\Domain\Model\ToolCall;
use App\Tool\Domain\Model\ToolDescriptor;
use App\Tool\Domain\Model\ValueObject\ToolCallId;
use App\Tool\Domain\Model\ValueObject\ToolName;
use App\Tool\Domain\Port\ToolExecutionContext;

/**
 * Sole approved bridge between Assistant and Tool contexts (ADR-0004).
 *
 * Lives in `Assistant/Infrastructure/` — the only place where it is legal to
 * import from `App\Tool\*` while wearing an Assistant hat. Everywhere else in
 * Assistant goes through the `ToolGateway` port.
 */
final readonly class AssistantToolGatewayAdapter implements ToolGateway
{
    private const int DEFAULT_MAX_OUTPUT_BYTES = 262144; // 256 KiB

    public function __construct(
        private ListToolsHandler $listTools,
        private ExecuteToolHandler $executeTool,
        private Clock $clock,
        private string $projectRoot,
        private int $maxOutputBytes = self::DEFAULT_MAX_OUTPUT_BYTES,
    ) {
    }

    public function availableTools(): array
    {
        return array_map(
            static fn (ToolDescriptor $d): ToolAdvertisement => new ToolAdvertisement(
                name: $d->name->value,
                description: $d->description,
                parameters: $d->parameters->value,
            ),
            ($this->listTools)(new ListToolsQuery()),
        );
    }

    public function execute(ToolCallRequest $request, string $sessionId): ToolResultDto
    {
        // LLM providers send tool_call ids in their own format (Ollama sends
        // plain integers like "0"; OpenAI uses "call_…"; Anthropic uses
        // "toolu_…"). Our internal Tool\Domain forces a "tcl_" prefix on
        // ToolCallId — so we normalize at this boundary and keep the original
        // id verbatim in the outbound ToolResultDto so the LLM matches it on
        // the next turn.
        $normalizedId = str_starts_with($request->id, ToolCallId::PREFIX)
            ? $request->id
            : ToolCallId::PREFIX.$request->id;

        try {
            $call = new ToolCall(
                ToolCallId::fromString($normalizedId),
                ToolName::of($request->name),
                $request->arguments,
            );
        } catch (\InvalidArgumentException $e) {
            return ToolResultDto::error($request->id, \sprintf('invalid tool call: %s', $e->getMessage()));
        }

        try {
            $result = ($this->executeTool)(new ExecuteToolCommand(
                $call,
                new ToolExecutionContext($this->projectRoot, $this->maxOutputBytes, $this->clock->now(), $sessionId),
            ));
        } catch (ToolNotFound $e) {
            return ToolResultDto::error($request->id, $e->getMessage());
        }

        return new ToolResultDto(
            toolCallId: $request->id,
            output: $result->output,
            isError: $result->isError,
        );
    }
}
