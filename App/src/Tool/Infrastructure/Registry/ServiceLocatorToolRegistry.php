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
 * here we accept a flat iterable<Tool> and build the map ourselves once.
 */
final class ServiceLocatorToolRegistry implements ToolRegistry
{
    /** @var array<string, ToolDescriptor> */
    private array $descriptors;

    /**
     * @param iterable<Tool> $tools
     */
    public function __construct(
        iterable $tools,
        private readonly ContainerInterface $locator,
    ) {
        $this->descriptors = [];
        foreach ($tools as $tool) {
            $descriptor = $tool->descriptor();
            $name = $descriptor->name->value;
            if (isset($this->descriptors[$name])) {
                throw new \LogicException(\sprintf('Duplicate tool name "%s" in registry.', $name));
            }
            $this->descriptors[$name] = $descriptor;
        }
        ksort($this->descriptors);
    }

    /**
     * @return list<ToolDescriptor>
     */
    public function list(): array
    {
        return array_values($this->descriptors);
    }

    public function find(ToolName $name): ?Tool
    {
        if (!isset($this->descriptors[$name->value])) {
            return null;
        }

        $tool = $this->locator->get($name->value);
        \assert($tool instanceof Tool);

        return $tool;
    }
}
