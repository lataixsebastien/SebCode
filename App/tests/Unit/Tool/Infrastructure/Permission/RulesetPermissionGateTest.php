<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Infrastructure\Permission;

use App\Tests\Support\Tool\Doubles\FakePermissionPrompter;
use App\Tool\Domain\Exception\PermissionDenied;
use App\Tool\Domain\Model\PermissionRule;
use App\Tool\Domain\Model\PermissionRuleset;
use App\Tool\Domain\Model\ValueObject\PermissionAction;
use App\Tool\Domain\Model\ValueObject\PermissionRequest;
use App\Tool\Domain\Model\ValueObject\PermissionType;
use App\Tool\Infrastructure\Permission\RulesetPermissionGate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RulesetPermissionGate::class)]
final class RulesetPermissionGateTest extends TestCase
{
    public function testAllowRulePassesWithoutPrompting(): void
    {
        $prompter = new FakePermissionPrompter(PermissionAction::Deny);
        $gate = new RulesetPermissionGate(
            new PermissionRuleset([new PermissionRule('edit', '*', PermissionAction::Allow)]),
            $prompter,
        );

        $gate->ensure(new PermissionRequest(PermissionType::Edit, ['src/a.php']));

        self::assertSame([], $prompter->prompts, 'An allow rule must not reach the prompter.');
    }

    public function testDenyRuleThrowsWithoutPrompting(): void
    {
        $prompter = new FakePermissionPrompter(PermissionAction::Allow);
        $gate = new RulesetPermissionGate(
            new PermissionRuleset([new PermissionRule('bash', '*', PermissionAction::Deny)]),
            $prompter,
        );

        $this->expectException(PermissionDenied::class);

        try {
            $gate->ensure(new PermissionRequest(PermissionType::Bash, ['rm -rf /']));
        } finally {
            self::assertSame([], $prompter->prompts, 'A deny rule must short-circuit before prompting.');
        }
    }

    public function testAskDelegatesToPrompterAllow(): void
    {
        $prompter = new FakePermissionPrompter(PermissionAction::Allow);
        $gate = new RulesetPermissionGate(new PermissionRuleset(), $prompter);

        $gate->ensure(new PermissionRequest(PermissionType::Edit, ['src/a.php']));

        self::assertCount(1, $prompter->prompts);
        self::assertSame('src/a.php', $prompter->prompts[0]['subject']);
    }

    public function testAskDelegatesToPrompterDenyThrows(): void
    {
        $gate = new RulesetPermissionGate(
            new PermissionRuleset(),
            new FakePermissionPrompter(PermissionAction::Deny),
        );

        $this->expectException(PermissionDenied::class);

        $gate->ensure(new PermissionRequest(PermissionType::Edit, ['src/a.php']));
    }

    public function testEveryPatternMustPass(): void
    {
        // First pattern allowed by rule, second falls through to a denying prompter.
        $gate = new RulesetPermissionGate(
            new PermissionRuleset([new PermissionRule('edit', 'allowed/**', PermissionAction::Allow)]),
            new FakePermissionPrompter(PermissionAction::Deny),
        );

        $this->expectException(PermissionDenied::class);

        $gate->ensure(new PermissionRequest(PermissionType::Edit, ['allowed/a.php', 'other/b.php']));
    }
}
