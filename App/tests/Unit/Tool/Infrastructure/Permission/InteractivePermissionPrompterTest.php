<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Infrastructure\Permission;

use App\Tests\Support\Tool\Doubles\FakePermissionConsole;
use App\Tests\Support\Tool\Doubles\FakePermissionPrompter;
use App\Tool\Domain\Model\ValueObject\PermissionAction;
use App\Tool\Domain\Model\ValueObject\PermissionChoice;
use App\Tool\Domain\Model\ValueObject\PermissionRequest;
use App\Tool\Domain\Model\ValueObject\PermissionType;
use App\Tool\Infrastructure\Permission\InMemoryPermissionGrants;
use App\Tool\Infrastructure\Permission\InteractivePermissionPrompter;
use App\Tool\Infrastructure\Permission\MutablePermissionConsoleRegistry;
use App\Tool\Infrastructure\Permission\NullPermissionConsole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractivePermissionPrompter::class)]
final class InteractivePermissionPrompterTest extends TestCase
{
    public function testFallsBackToDefaultWhenNoConsoleIsActive(): void
    {
        $fallback = new FakePermissionPrompter(PermissionAction::Deny);
        $prompter = new InteractivePermissionPrompter(
            $this->registryWith(null),
            new InMemoryPermissionGrants(),
            $fallback,
        );

        $action = $prompter->prompt($this->bashRequest(), 'git status');

        self::assertSame(PermissionAction::Deny, $action);
        self::assertCount(1, $fallback->prompts, 'Inactive console must defer to the fallback.');
    }

    public function testOnceAllowsWithoutPersisting(): void
    {
        $grants = new InMemoryPermissionGrants();
        $console = new FakePermissionConsole(active: true, choice: PermissionChoice::AllowOnce);
        $prompter = new InteractivePermissionPrompter(
            $this->registryWith($console),
            $grants,
            new FakePermissionPrompter(PermissionAction::Deny),
        );

        $action = $prompter->prompt($this->bashRequest(), 'git status');

        self::assertSame(PermissionAction::Allow, $action);
        self::assertFalse($grants->isGranted(PermissionType::Bash, 'git log'), '"once" must not persist a grant.');
    }

    public function testAlwaysAllowsAndPersistsTheAlwaysPatterns(): void
    {
        $grants = new InMemoryPermissionGrants();
        $console = new FakePermissionConsole(active: true, choice: PermissionChoice::AllowAlways);
        $prompter = new InteractivePermissionPrompter(
            $this->registryWith($console),
            $grants,
            new FakePermissionPrompter(PermissionAction::Deny),
        );

        $action = $prompter->prompt($this->bashRequest(), 'git status');

        self::assertSame(PermissionAction::Allow, $action);
        self::assertTrue($grants->isGranted(PermissionType::Bash, 'git log'), '"always" must persist the git * grant.');
    }

    public function testRejectDenies(): void
    {
        $console = new FakePermissionConsole(active: true, choice: PermissionChoice::Reject);
        $prompter = new InteractivePermissionPrompter(
            $this->registryWith($console),
            new InMemoryPermissionGrants(),
            new FakePermissionPrompter(PermissionAction::Allow),
        );

        self::assertSame(PermissionAction::Deny, $prompter->prompt($this->bashRequest(), 'git status'));
    }

    private function registryWith(?FakePermissionConsole $console): MutablePermissionConsoleRegistry
    {
        $registry = new MutablePermissionConsoleRegistry(new NullPermissionConsole());
        if (null !== $console) {
            $registry->attach($console);
        }

        return $registry;
    }

    private function bashRequest(): PermissionRequest
    {
        return new PermissionRequest(PermissionType::Bash, ['git status'], [], ['git *']);
    }
}
