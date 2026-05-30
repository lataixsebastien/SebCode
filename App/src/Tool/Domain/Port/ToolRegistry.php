<?php

declare(strict_types=1);

namespace App\Tool\Domain\Port;

use App\Tool\Domain\Model\ToolDescriptor;
use App\Tool\Domain\Model\ValueObject\ToolName;

/**
 * Catalogue of every tool the assistant may advertise to the LLM.
 *
 * Implementations decide how tools are discovered (DI tag, static map,
 * MCP server, plugin scan…). The catalogue is read-only from the Domain's
 * point of view — extension happens at the Infrastructure layer.
 */
interface ToolRegistry
{
    /**
     * @return list<ToolDescriptor> in stable order (so the LLM sees the same set across turns)
     */
    public function list(): array;

    public function find(ToolName $name): ?Tool;
}
