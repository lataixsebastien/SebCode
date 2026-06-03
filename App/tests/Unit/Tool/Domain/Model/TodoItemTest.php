<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Domain\Model;

use App\Tool\Domain\Model\TodoItem;
use App\Tool\Domain\Model\ValueObject\TodoPriority;
use App\Tool\Domain\Model\ValueObject\TodoStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TodoItem::class)]
final class TodoItemTest extends TestCase
{
    public function testHoldsContentStatusAndPriority(): void
    {
        $item = new TodoItem('wire the CLI', TodoStatus::InProgress, TodoPriority::High);

        self::assertSame('wire the CLI', $item->content);
        self::assertSame(TodoStatus::InProgress, $item->status);
        self::assertSame(TodoPriority::High, $item->priority);
    }

    public function testRejectsEmptyContent(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TodoItem('   ', TodoStatus::Pending, TodoPriority::Low);
    }
}
