<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Domain\Model\ValueObject;

use App\Tool\Domain\Model\ValueObject\PermissionRequest;
use App\Tool\Domain\Model\ValueObject\PermissionType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PermissionRequest::class)]
final class PermissionRequestTest extends TestCase
{
    public function testHoldsTypePatternsAndMetadata(): void
    {
        $request = new PermissionRequest(
            PermissionType::Edit,
            ['src/a.php', 'src/b.php'],
            ['diff' => '@@ -1 +1 @@'],
        );

        self::assertSame(PermissionType::Edit, $request->type);
        self::assertSame(['src/a.php', 'src/b.php'], $request->patterns);
        self::assertSame(['diff' => '@@ -1 +1 @@'], $request->metadata);
    }

    public function testRejectsEmptyPatternList(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PermissionRequest(PermissionType::Edit, []);
    }

    public function testRejectsEmptyPatternString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PermissionRequest(PermissionType::Bash, ['ok', '']);
    }
}
