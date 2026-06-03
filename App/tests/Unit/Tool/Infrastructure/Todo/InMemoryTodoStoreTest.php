<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Infrastructure\Todo;

use App\Tool\Domain\Model\TodoItem;
use App\Tool\Domain\Model\ValueObject\TodoPriority;
use App\Tool\Domain\Model\ValueObject\TodoStatus;
use App\Tool\Infrastructure\Todo\InMemoryTodoStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryTodoStore::class)]
final class InMemoryTodoStoreTest extends TestCase
{
    public function testStartsEmpty(): void
    {
        self::assertSame([], (new InMemoryTodoStore())->all());
    }

    public function testReplaceStoresTheList(): void
    {
        $store = new InMemoryTodoStore();
        $items = [
            new TodoItem('a', TodoStatus::Pending, TodoPriority::Low),
            new TodoItem('b', TodoStatus::InProgress, TodoPriority::High),
        ];

        $store->replace($items);

        self::assertSame($items, $store->all());
    }

    public function testSecondReplaceOverwritesTheFirst(): void
    {
        $store = new InMemoryTodoStore();
        $store->replace([new TodoItem('old', TodoStatus::Pending, TodoPriority::Low)]);

        $fresh = [new TodoItem('new', TodoStatus::Completed, TodoPriority::Medium)];
        $store->replace($fresh);

        self::assertSame($fresh, $store->all());
    }
}
