<?php

declare(strict_types=1);

namespace App\Tool\Domain\Port;

use App\Tool\Domain\Exception\InvalidToolArguments;
use App\Tool\Domain\Exception\ToolExecutionFailed;
use App\Tool\Domain\Model\ToolCall;
use App\Tool\Domain\Model\ToolDescriptor;
use App\Tool\Domain\Model\ToolResult;

/**
 * A concrete capability the assistant can invoke.
 *
 * Tools are pure adapters from the LLM's intent (a ToolCall) to a textual
 * outcome (a ToolResult). They MUST be side-effect-safe within their
 * declared scope (e.g. read-only tools never write to disk).
 *
 * Implementations live in `Tool\Infrastructure\Tool\` and are tagged
 * `app.tool` in services.yaml so the `ServiceLocatorToolRegistry` can
 * discover them.
 */
interface Tool
{
    public function descriptor(): ToolDescriptor;

    /**
     * @throws InvalidToolArguments if the call arguments don't match the tool's schema
     * @throws ToolExecutionFailed for unrecoverable runtime errors that should abort the loop
     */
    public function execute(ToolCall $call, ToolExecutionContext $context): ToolResult;
}
