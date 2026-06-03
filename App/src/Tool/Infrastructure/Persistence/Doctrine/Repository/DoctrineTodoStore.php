<?php

declare(strict_types=1);

namespace App\Tool\Infrastructure\Persistence\Doctrine\Repository;

use App\Tool\Domain\Model\TodoItem;
use App\Tool\Domain\Model\ValueObject\TodoPriority;
use App\Tool\Domain\Model\ValueObject\TodoStatus;
use App\Tool\Domain\Port\TodoStore;
use App\Tool\Infrastructure\Persistence\Doctrine\Entity\TodoEntity;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine-backed {@see TodoStore}: persists each session's list in the
 * `tool_todos` table so todos survive across runs.
 *
 * Full-list semantics: replace() deletes the session's rows then re-inserts
 * the new list in order — mirroring opencode's delete-then-insert transaction.
 */
final readonly class DoctrineTodoStore implements TodoStore
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function replace(string $sessionId, array $todos): void
    {
        // Load + remove the session's rows through the UnitOfWork (so they leave
        // the identity map on flush), THEN insert the new list. A bulk DQL
        // delete would leave managed entities behind and collide with the new
        // rows' composite ids on a second replace() within the same EM.
        $this->em->wrapInTransaction(static function (EntityManagerInterface $em) use ($sessionId, $todos): void {
            foreach ($em->getRepository(TodoEntity::class)->findBy(['sessionId' => $sessionId]) as $existing) {
                $em->remove($existing);
            }
            $em->flush();

            foreach ($todos as $position => $todo) {
                $entity = new TodoEntity();
                $entity->sessionId = $sessionId;
                $entity->position = $position;
                $entity->content = $todo->content;
                $entity->status = $todo->status->value;
                $entity->priority = $todo->priority->value;
                $em->persist($entity);
            }
            $em->flush();
        });
    }

    public function all(string $sessionId): array
    {
        /** @var list<TodoEntity> $rows */
        $rows = $this->em->createQueryBuilder()
            ->select('t')
            ->from(TodoEntity::class, 't')
            ->where('t.sessionId = :sid')
            ->setParameter('sid', $sessionId)
            ->orderBy('t.position', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (TodoEntity $e): TodoItem => new TodoItem(
                $e->content,
                TodoStatus::from($e->status),
                TodoPriority::from($e->priority),
            ),
            $rows,
        );
    }
}
