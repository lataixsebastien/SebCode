<?php

declare(strict_types=1);

namespace App\Tests\Support\Tool\Doubles;

use App\Tool\Domain\Model\ToolDescriptor;
use App\Tool\Domain\Model\ValueObject\ToolName;
use App\Tool\Domain\Port\Tool;
use App\Tool\Domain\Port\ToolRegistry;

final class InMemoryToolRegistry implements ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function register(Tool $tool): void
    {
        $this->tools[$tool->descriptor()->name->value] = $tool;
    }

    /**
     * @return list<ToolDescriptor>
     */
    public function list(): array
    {
        return array_values(array_map(
            static fn (Tool $t): ToolDescriptor => $t->descriptor(),
            $this->tools,
        ));
    }

    public function find(ToolName $name): ?Tool
    {
        return $this->tools[$name->value] ?? null;
    }
}
