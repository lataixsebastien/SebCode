<?php

declare(strict_types=1);

namespace App\Tool\Infrastructure\Registry;

use App\Tool\Domain\Model\ToolDescriptor;
use App\Tool\Domain\Model\ValueObject\ToolName;
use App\Tool\Domain\Port\Tool;
use App\Tool\Domain\Port\ToolRegistry;
use Psr\Container\ContainerInterface;

/**
 * ToolRegistry backed by a Symfony service-locator built from the `app.tool`
 * DI tag. Every class implementing `Tool\Domain\Port\Tool` is automatically
 * tagged via `_instanceof` in `config/services.yaml`.
 *
 * The locator is indexed by tool name (declared via `descriptor()->name`).
 * Doing the indexing in services.yaml would require static introspection;
 * here we accept a flat iterable<Tool> and build the map ourselves — but
 * LAZILY, on first list()/find(). Indexing instantiates every tool, and a tool
 * may (via the `task` tool → SubAgentRunner → ToolGateway) depend back on a
 * service whose construction builds this registry; deferring the iteration
 * past construction breaks that bootstrap cycle.
 */
final class ServiceLocatorToolRegistry implements ToolRegistry
{
    /** @var iterable<Tool> */
    private iterable $tools;

    /** @var array<string, ToolDescriptor>|null */
    private ?array $descriptors = null;

    /**
     * @param iterable<Tool> $tools
     */
    public function __construct(
        iterable $tools,
        private readonly ContainerInterface $locator,
    ) {
        $this->tools = $tools;
    }

    /**
     * @return list<ToolDescriptor>
     */
    public function list(): array
    {
        return array_values($this->descriptors());
    }

    public function find(ToolName $name): ?Tool
    {
        if (!isset($this->descriptors()[$name->value])) {
            return null;
        }

        $tool = $this->locator->get($name->value);
        \assert($tool instanceof Tool);

        return $tool;
    }

    /**
     * @return array<string, ToolDescriptor>
     */
    private function descriptors(): array
    {
        if (null !== $this->descriptors) {
            return $this->descriptors;
        }

        $descriptors = [];
        foreach ($this->tools as $tool) {
            $descriptor = $tool->descriptor();
            $name = $descriptor->name->value;
            if (isset($descriptors[$name])) {
                throw new \LogicException(\sprintf('Duplicate tool name "%s" in registry.', $name));
            }
            $descriptors[$name] = $descriptor;
        }
        ksort($descriptors);

        return $this->descriptors = $descriptors;
    }
}
