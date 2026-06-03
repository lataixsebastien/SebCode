<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Port;

use App\Assistant\Domain\Model\ValueObject\ToolAdvertisement;
use App\Assistant\Domain\Model\ValueObject\ToolCallRequest;
use App\Assistant\Domain\Model\ValueObject\ToolResultDto;

/**
 * Inter-context bridge: lets `Assistant\Application` discover what tools are
 * available and execute them, without depending on `Tool\Domain` directly.
 *
 * The concrete implementation lives in
 * `Assistant\Infrastructure\Tool\AssistantToolGatewayAdapter` and delegates to
 * `Tool\Application\{ListToolsHandler, ExecuteToolHandler}`, translating
 * DTOs at the boundary. See ADR-0004.
 */
interface ToolGateway
{
    /**
     * Returns the tools advertised to the LLM on every turn.
     *
     * @return list<ToolAdvertisement>
     */
    public function availableTools(): array;

    /**
     * Executes one tool call coming back from the LLM.
     *
     * Soft failures (bad arguments, missing file, sandbox refusal) are returned
     * as `ToolResultDto::error(...)`. Hard failures (registry corrupted, etc.)
     * propagate as exceptions so the assistant loop can abort.
     *
     * `$sessionId` scopes session-stateful tools (e.g. todowrite) to the caller.
     */
    public function execute(ToolCallRequest $request, string $sessionId): ToolResultDto;
}
