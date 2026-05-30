<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Application\Query;

use App\Tests\Support\Tool\Doubles\FakeTool;
use App\Tests\Support\Tool\Doubles\InMemoryToolRegistry;
use App\Tool\Application\Query\ListToolsHandler;
use App\Tool\Application\Query\ListToolsQuery;
use App\Tool\Domain\Model\ToolDescriptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListToolsHandler::class)]
#[CoversClass(ListToolsQuery::class)]
final class ListToolsHandlerTest extends TestCase
{
    public function testReturnsDescriptorsOfRegisteredTools(): void
    {
        $registry = new InMemoryToolRegistry();
        $registry->register(new FakeTool('glob'));
        $registry->register(new FakeTool('read'));

        $descriptors = (new ListToolsHandler($registry))(new ListToolsQuery());

        self::assertCount(2, $descriptors);
        $names = array_map(static fn (ToolDescriptor $d): string => $d->name->value, $descriptors);
        self::assertSame(['glob', 'read'], $names);
    }

    public function testReturnsEmptyListWhenRegistryIsEmpty(): void
    {
        self::assertSame(
            [],
            (new ListToolsHandler(new InMemoryToolRegistry()))(new ListToolsQuery()),
        );
    }
}
