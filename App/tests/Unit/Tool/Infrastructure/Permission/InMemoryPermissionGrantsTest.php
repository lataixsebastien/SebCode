<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Infrastructure\Permission;

use App\Tool\Domain\Model\ValueObject\PermissionType;
use App\Tool\Infrastructure\Permission\InMemoryPermissionGrants;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryPermissionGrants::class)]
final class InMemoryPermissionGrantsTest extends TestCase
{
    public function testUngrantedSubjectIsNotGranted(): void
    {
        $grants = new InMemoryPermissionGrants();

        self::assertFalse($grants->isGranted(PermissionType::Bash, 'git status'));
    }

    public function testGrantedPrefixMatchesViaWildcard(): void
    {
        $grants = new InMemoryPermissionGrants();
        $grants->grant(PermissionType::Bash, 'git *');

        self::assertTrue($grants->isGranted(PermissionType::Bash, 'git status'));
        self::assertTrue($grants->isGranted(PermissionType::Bash, 'git'));
        self::assertFalse($grants->isGranted(PermissionType::Bash, 'npm install'));
    }

    public function testGrantsAreScopedToTheirType(): void
    {
        $grants = new InMemoryPermissionGrants();
        $grants->grant(PermissionType::Bash, '*');

        self::assertTrue($grants->isGranted(PermissionType::Bash, 'anything'));
        self::assertFalse($grants->isGranted(PermissionType::Edit, 'anything'));
    }

    public function testGrantingTheSamePatternTwiceIsIdempotent(): void
    {
        $grants = new InMemoryPermissionGrants();
        $grants->grant(PermissionType::Bash, 'ls *');
        $grants->grant(PermissionType::Bash, 'ls *');

        self::assertTrue($grants->isGranted(PermissionType::Bash, 'ls -la'));
    }
}
