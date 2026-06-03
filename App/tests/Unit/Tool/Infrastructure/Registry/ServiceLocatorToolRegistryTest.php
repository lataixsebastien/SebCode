<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Infrastructure\Registry;

use App\Tests\Support\Tool\Doubles\FakeTool;
use App\Tool\Domain\Model\ValueObject\ToolName;
use App\Tool\Domain\Port\Tool;
use App\Tool\Infrastructure\Registry\ServiceLocatorToolRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[CoversClass(ServiceLocatorToolRegistry::class)]
final class ServiceLocatorToolRegistryTest extends TestCase
{
    public function testListReturnsDescriptorsInAlphabeticalOrder(): void
    {
        $registry = new ServiceLocatorToolRegistry(
            [new FakeTool('read'), new FakeTool('glob')],
            $this->emptyLocator(),
        );

        $names = array_map(static fn ($d) => $d->name->value, $registry->list());

        self::assertSame(['glob', 'read'], $names);
    }

    public function testFindReturnsNullForUnknownTool(): void
    {
        $registry = new ServiceLocatorToolRegistry([new FakeTool('glob')], $this->emptyLocator());

        self::assertNull($registry->find(ToolName::of('read')));
    }

    public function testFindFetchesFromLocator(): void
    {
        $glob = new FakeTool('glob');
        $locator = $this->locatorWith(['glob' => $glob]);

        $registry = new ServiceLocatorToolRegistry([$glob], $locator);

        self::assertSame($glob, $registry->find(ToolName::of('glob')));
    }

    public function testThrowsOnDuplicateName(): void
    {
        $registry = new ServiceLocatorToolRegistry(
            [new FakeTool('fake'), new FakeTool('fake')],
            $this->emptyLocator(),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Duplicate tool name "fake"/');

        // Indexing is lazy: the clash surfaces on first list().
        $registry->list();
    }

    private function emptyLocator(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                throw new \LogicException('empty locator');
            }

            public function has(string $id): bool
            {
                return false;
            }
        };
    }

    /**
     * @param array<string, Tool> $services
     */
    private function locatorWith(array $services): ContainerInterface
    {
        return new class($services) implements ContainerInterface {
            /**
             * @param array<string, Tool> $services
             */
            public function __construct(private readonly array $services)
            {
            }

            public function get(string $id): Tool
            {
                if (!isset($this->services[$id])) {
                    throw new \LogicException(\sprintf('service "%s" not in test locator', $id));
                }

                return $this->services[$id];
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };
    }
}
