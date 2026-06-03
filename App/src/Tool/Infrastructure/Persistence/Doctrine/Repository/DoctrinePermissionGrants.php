<?php

declare(strict_types=1);

namespace App\Tool\Infrastructure\Persistence\Doctrine\Repository;

use App\Tool\Domain\Model\ValueObject\PermissionType;
use App\Tool\Domain\Port\PermissionGrants;
use App\Tool\Domain\Service\WildcardMatcher;
use App\Tool\Infrastructure\Persistence\Doctrine\Entity\PermissionGrantEntity;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Permission grants persisted per project (so an "always" choice survives
 * across runs), backed by the `tool_permission_grants` table.
 *
 * The project's grants are loaded once (lazily) and cached in the instance;
 * grant() writes through to the DB and the cache. Matching reuses
 * {@see WildcardMatcher}, identical to the in-memory store.
 */
final class DoctrinePermissionGrants implements PermissionGrants
{
    /** @var list<array{type: PermissionType, pattern: string}>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $projectRoot,
    ) {
    }

    public function grant(PermissionType $type, string $pattern): void
    {
        foreach ($this->load() as $grant) {
            if ($grant['type'] === $type && $grant['pattern'] === $pattern) {
                return;
            }
        }

        $entity = new PermissionGrantEntity();
        $entity->projectRoot = $this->projectRoot;
        $entity->type = $type->value;
        $entity->pattern = $pattern;
        $this->em->persist($entity);
        $this->em->flush();

        $this->cache[] = ['type' => $type, 'pattern' => $pattern];
    }

    public function isGranted(PermissionType $type, string $subject): bool
    {
        foreach ($this->load() as $grant) {
            if ($grant['type'] === $type && WildcardMatcher::matches($subject, $grant['pattern'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{type: PermissionType, pattern: string}>
     */
    private function load(): array
    {
        if (null !== $this->cache) {
            return $this->cache;
        }

        /** @var list<PermissionGrantEntity> $rows */
        $rows = $this->em->createQueryBuilder()
            ->select('g')
            ->from(PermissionGrantEntity::class, 'g')
            ->where('g.projectRoot = :root')
            ->setParameter('root', $this->projectRoot)
            ->getQuery()
            ->getResult();

        $this->cache = [];
        foreach ($rows as $row) {
            $type = PermissionType::tryFrom($row->type);
            if (null !== $type) {
                $this->cache[] = ['type' => $type, 'pattern' => $row->pattern];
            }
        }

        return $this->cache;
    }
}
