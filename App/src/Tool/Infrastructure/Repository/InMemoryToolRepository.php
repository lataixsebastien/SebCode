<?php

declare(strict_types=1);

namespace SebCode\Tool\Infrastructure\Repository;

use SebCode\Tool\Domain\Exception\ToolNotFound;
use SebCode\Tool\Domain\Model\ToolDescriptor;
use SebCode\Tool\Domain\Port\Tool;
use SebCode\Tool\Domain\Port\ToolRepository;

final class InMemoryToolRepository implements ToolRepository
{
    /**
     * @var array<string, Tool>
     */
    private array $tools = [];

    public function save(Tool $tool): void
    {
        $name = $tool->descriptor()->name;
        if (isset($this->tools[$name])) {
            throw new \InvalidArgumentException(sprintf('Tool "%s" is already registered.', $name));
        }

        $this->tools[$name] = $tool;
        ksort($this->tools);
    }

    public function get(string $name): Tool
    {
        return $this->tools[$name] ?? throw ToolNotFound::named($name);
    }

    /**
     * @return list<ToolDescriptor>
     */
    public function descriptors(): array
    {
        return array_values(array_map(
            static fn (Tool $tool): ToolDescriptor => $tool->descriptor(),
            $this->tools,
        ));
    }

    public function availableDescriptors(string $mode): array
    {
        return array_values(array_filter(
            $this->descriptors(),
            static fn (ToolDescriptor $descriptor): bool => $descriptor->isAvailableForMode($mode),
        ));
    }
}
