<?php

declare(strict_types=1);

namespace App\Tests\Integration\Tool\Persistence;

use App\Tool\Domain\Model\TodoItem;
use App\Tool\Domain\Model\ValueObject\TodoPriority;
use App\Tool\Domain\Model\ValueObject\TodoStatus;
use App\Tool\Infrastructure\Persistence\Doctrine\Entity\TodoEntity;
use App\Tool\Infrastructure\Persistence\Doctrine\Repository\DoctrineTodoStore;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration test against the real Postgres test database (app_test).
 *
 * Requires the test schema to be migrated:
 *   bin/console doctrine:database:create --env=test --if-not-exists
 *   bin/console doctrine:migrations:migrate --env=test --no-interaction
 */
#[CoversClass(DoctrineTodoStore::class)]
final class DoctrineTodoStoreTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private DoctrineTodoStore $store;

    protected function setUp(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;
        $this->store = new DoctrineTodoStore($em);

        $this->em->createQueryBuilder()->delete(TodoEntity::class, 't')->getQuery()->execute();
        $this->em->clear();
    }

    public function testRoundTripsAListForASession(): void
    {
        $this->store->replace('ses_int', [
            new TodoItem('read', TodoStatus::Completed, TodoPriority::High),
            new TodoItem('write', TodoStatus::InProgress, TodoPriority::Medium),
        ]);
        $this->em->clear();

        $loaded = $this->store->all('ses_int');

        self::assertCount(2, $loaded);
        self::assertSame('read', $loaded[0]->content);
        self::assertSame(TodoStatus::Completed, $loaded[0]->status);
        self::assertSame(TodoStatus::InProgress, $loaded[1]->status);
        self::assertSame(TodoPriority::Medium, $loaded[1]->priority);
    }

    public function testReplaceOverwritesThePreviousList(): void
    {
        $this->store->replace('ses_int', [new TodoItem('old', TodoStatus::Pending, TodoPriority::Low)]);
        $this->store->replace('ses_int', [new TodoItem('new', TodoStatus::Completed, TodoPriority::Low)]);
        $this->em->clear();

        $loaded = $this->store->all('ses_int');

        self::assertCount(1, $loaded);
        self::assertSame('new', $loaded[0]->content);
    }

    public function testSessionsAreIsolated(): void
    {
        $this->store->replace('ses_a', [new TodoItem('a', TodoStatus::Pending, TodoPriority::Low)]);
        $this->store->replace('ses_b', [new TodoItem('b', TodoStatus::Pending, TodoPriority::Low)]);
        $this->em->clear();

        self::assertSame('a', $this->store->all('ses_a')[0]->content);
        self::assertSame('b', $this->store->all('ses_b')[0]->content);
        self::assertSame([], $this->store->all('ses_unknown'));
    }

    protected function tearDown(): void
    {
        $this->em->createQueryBuilder()->delete(TodoEntity::class, 't')->getQuery()->execute();
        parent::tearDown();
    }
}
