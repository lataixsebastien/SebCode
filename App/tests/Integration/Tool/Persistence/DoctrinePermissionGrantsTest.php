<?php

declare(strict_types=1);

namespace App\Tests\Integration\Tool\Persistence;

use App\Tool\Domain\Model\ValueObject\PermissionType;
use App\Tool\Infrastructure\Persistence\Doctrine\Entity\PermissionGrantEntity;
use App\Tool\Infrastructure\Persistence\Doctrine\Repository\DoctrinePermissionGrants;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration test against the Postgres test database (app_test). Requires the
 * test schema to be migrated (see DoctrineTodoStoreTest).
 */
#[CoversClass(DoctrinePermissionGrants::class)]
final class DoctrinePermissionGrantsTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;

        $this->em->createQueryBuilder()->delete(PermissionGrantEntity::class, 'g')->getQuery()->execute();
        $this->em->clear();
    }

    public function testGrantPersistsAndIsVisibleToAFreshInstance(): void
    {
        (new DoctrinePermissionGrants($this->em, '/proj'))->grant(PermissionType::Bash, 'git *');
        $this->em->clear();

        // A new instance reloads from the DB — the grant survived.
        $fresh = new DoctrinePermissionGrants($this->em, '/proj');
        self::assertTrue($fresh->isGranted(PermissionType::Bash, 'git status'));
        self::assertFalse($fresh->isGranted(PermissionType::Bash, 'npm install'));
    }

    public function testGrantsAreScopedToTheProject(): void
    {
        (new DoctrinePermissionGrants($this->em, '/proj-a'))->grant(PermissionType::Bash, '*');
        $this->em->clear();

        self::assertTrue((new DoctrinePermissionGrants($this->em, '/proj-a'))->isGranted(PermissionType::Bash, 'anything'));
        self::assertFalse((new DoctrinePermissionGrants($this->em, '/proj-b'))->isGranted(PermissionType::Bash, 'anything'));
    }

    public function testGrantIsIdempotent(): void
    {
        $grants = new DoctrinePermissionGrants($this->em, '/proj');
        $grants->grant(PermissionType::Edit, 'src/*');
        $grants->grant(PermissionType::Edit, 'src/*');
        $this->em->clear();

        $rows = $this->em->getRepository(PermissionGrantEntity::class)->findBy(['projectRoot' => '/proj']);
        self::assertCount(1, $rows);
    }

    protected function tearDown(): void
    {
        $this->em->createQueryBuilder()->delete(PermissionGrantEntity::class, 'g')->getQuery()->execute();
        parent::tearDown();
    }
}
