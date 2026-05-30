<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Persistence\Doctrine\Repository;

use App\Assistant\Domain\Model\Message;
use App\Assistant\Domain\Model\ValueObject\SessionId;
use App\Assistant\Domain\Port\MessageRepository;
use App\Assistant\Infrastructure\Persistence\Doctrine\Entity\MessageEntity;
use App\Assistant\Infrastructure\Persistence\Doctrine\Mapper\MessageMapper;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineMessageRepository implements MessageRepository
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageMapper $mapper,
    ) {
    }

    public function append(Message $message): void
    {
        $entity = $this->mapper->toEntity($message);
        $this->em->persist($entity);
        $this->em->flush();
    }

    /**
     * @return list<Message>
     */
    public function forSession(SessionId $sessionId): array
    {
        /** @var list<MessageEntity> $entities */
        $entities = $this->em->createQueryBuilder()
            ->select('m')
            ->from(MessageEntity::class, 'm')
            ->where('m.sessionId = :sid')
            ->setParameter('sid', $sessionId->value)
            ->orderBy('m.createdAt', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            fn (MessageEntity $e) => $this->mapper->toAggregate($e),
            $entities,
        );
    }
}
