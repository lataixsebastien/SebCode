<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Persistence\Doctrine\Repository;

use App\Assistant\Domain\Model\Session;
use App\Assistant\Domain\Model\ValueObject\SessionId;
use App\Assistant\Domain\Port\SessionRepository;
use App\Assistant\Infrastructure\Persistence\Doctrine\Entity\SessionEntity;
use App\Assistant\Infrastructure\Persistence\Doctrine\Mapper\SessionMapper;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineSessionRepository implements SessionRepository
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SessionMapper $mapper,
    ) {
    }

    public function save(Session $session): void
    {
        $existing = $this->em->find(SessionEntity::class, $session->id->value);
        $entity = $this->mapper->toEntity($session, $existing);

        if (null === $existing) {
            $this->em->persist($entity);
        }

        $this->em->flush();
    }

    public function findById(SessionId $id): ?Session
    {
        $entity = $this->em->find(SessionEntity::class, $id->value);

        return null === $entity ? null : $this->mapper->toAggregate($entity);
    }

    /**
     * @return list<Session>
     */
    public function all(): array
    {
        /** @var list<SessionEntity> $entities */
        $entities = $this->em->createQueryBuilder()
            ->select('s')
            ->from(SessionEntity::class, 's')
            ->orderBy('s.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(
            fn (SessionEntity $e) => $this->mapper->toAggregate($e),
            $entities,
        );
    }

    public function delete(SessionId $id): void
    {
        $entity = $this->em->find(SessionEntity::class, $id->value);
        if (null === $entity) {
            return;
        }
        $this->em->remove($entity);
        $this->em->flush();
    }
}
