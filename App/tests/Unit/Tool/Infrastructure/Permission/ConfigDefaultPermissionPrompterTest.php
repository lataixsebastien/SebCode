<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Infrastructure\Permission;

use App\Tool\Domain\Model\ValueObject\PermissionAction;
use App\Tool\Domain\Model\ValueObject\PermissionRequest;
use App\Tool\Domain\Model\ValueObject\PermissionType;
use App\Tool\Infrastructure\Permission\ConfigDefaultPermissionPrompter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigDefaultPermissionPrompter::class)]
final class ConfigDefaultPermissionPrompterTest extends TestCase
{
    public function testDefaultsToDeny(): void
    {
        $prompter = ConfigDefaultPermissionPrompter::fromString('deny');

        self::assertSame(
            PermissionAction::Deny,
            $prompter->prompt(new PermissionRequest(PermissionType::Edit, ['x']), 'x'),
        );
    }

    public function testAllowWhenConfigured(): void
    {
        $prompter = ConfigDefaultPermissionPrompter::fromString('allow');

        self::assertSame(
            PermissionAction::Allow,
            $prompter->prompt(new PermissionRequest(PermissionType::Bash, ['ls']), 'ls'),
        );
    }

    public function testUnknownValueFallsBackToDeny(): void
    {
        $prompter = ConfigDefaultPermissionPrompter::fromString('garbage');

        self::assertSame(
            PermissionAction::Deny,
            $prompter->prompt(new PermissionRequest(PermissionType::Edit, ['x']), 'x'),
        );
    }

    public function testRejectsAskAsDefault(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ConfigDefaultPermissionPrompter(PermissionAction::Ask);
    }
}
