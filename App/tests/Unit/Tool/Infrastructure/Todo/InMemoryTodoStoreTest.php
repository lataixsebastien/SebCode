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
    public function testUnknownSessionIsEmpty(): void
    {
        self::assertSame([], (new InMemoryTodoStore())->all('ses_x'));
    }

    public function testReplaceStoresTheListForTheSession(): void
    {
        $store = new InMemoryTodoStore();
        $items = [
            new TodoItem('a', TodoStatus::Pending, TodoPriority::Low),
            new TodoItem('b', TodoStatus::InProgress, TodoPriority::High),
        ];

        $store->replace('ses_1', $items);

        self::assertSame($items, $store->all('ses_1'));
    }

    public function testSecondReplaceOverwritesTheFirst(): void
    {
        $store = new InMemoryTodoStore();
        $store->replace('ses_1', [new TodoItem('old', TodoStatus::Pending, TodoPriority::Low)]);

        $fresh = [new TodoItem('new', TodoStatus::Completed, TodoPriority::Medium)];
        $store->replace('ses_1', $fresh);

        self::assertSame($fresh, $store->all('ses_1'));
    }

    public function testSessionsAreIsolated(): void
    {
        $store = new InMemoryTodoStore();
        $store->replace('ses_1', [new TodoItem('one', TodoStatus::Pending, TodoPriority::Low)]);
        $store->replace('ses_2', [new TodoItem('two', TodoStatus::Pending, TodoPriority::Low)]);

        self::assertCount(1, $store->all('ses_1'));
        self::assertSame('two', $store->all('ses_2')[0]->content);
    }
}
