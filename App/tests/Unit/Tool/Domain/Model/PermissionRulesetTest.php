<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Domain\Model;

use App\Tool\Domain\Model\PermissionRule;
use App\Tool\Domain\Model\PermissionRuleset;
use App\Tool\Domain\Model\ValueObject\PermissionAction;
use App\Tool\Domain\Model\ValueObject\PermissionType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PermissionRuleset::class)]
#[CoversClass(PermissionRule::class)]
final class PermissionRulesetTest extends TestCase
{
    public function testEmptyRulesetDefaultsToAsk(): void
    {
        $ruleset = new PermissionRuleset();

        self::assertSame(
            PermissionAction::Ask,
            $ruleset->evaluate(PermissionType::Edit, 'src/foo.php'),
        );
    }

    public function testExplicitAllow(): void
    {
        $ruleset = new PermissionRuleset([
            new PermissionRule('edit', '*', PermissionAction::Allow),
        ]);

        self::assertSame(
            PermissionAction::Allow,
            $ruleset->evaluate(PermissionType::Edit, 'whatever'),
        );
    }

    public function testExplicitDeny(): void
    {
        $ruleset = new PermissionRuleset([
            new PermissionRule('bash', '*', PermissionAction::Deny),
        ]);

        self::assertSame(
            PermissionAction::Deny,
            $ruleset->evaluate(PermissionType::Bash, 'rm -rf /'),
        );
    }

    public function testLastMatchingRuleWins(): void
    {
        $ruleset = new PermissionRuleset([
            new PermissionRule('edit', '*', PermissionAction::Allow),
            new PermissionRule('edit', 'secret/**', PermissionAction::Deny),
        ]);

        self::assertSame(
            PermissionAction::Deny,
            $ruleset->evaluate(PermissionType::Edit, 'secret/keys.php'),
            'A later, more specific deny rule overrides an earlier allow.',
        );
        self::assertSame(
            PermissionAction::Allow,
            $ruleset->evaluate(PermissionType::Edit, 'src/app.php'),
            'Paths not matched by the later rule keep the earlier decision.',
        );
    }

    public function testTypeMustMatchToo(): void
    {
        $ruleset = new PermissionRuleset([
            new PermissionRule('edit', '*', PermissionAction::Allow),
        ]);

        self::assertSame(
            PermissionAction::Ask,
            $ruleset->evaluate(PermissionType::Bash, 'anything'),
            'An edit rule must not authorize a bash request.',
        );
    }

    public function testWildcardTypePattern(): void
    {
        $ruleset = new PermissionRuleset([
            new PermissionRule('*', '*', PermissionAction::Allow),
        ]);

        self::assertSame(
            PermissionAction::Allow,
            $ruleset->evaluate(PermissionType::ExternalDirectory, '/etc/passwd'),
        );
    }
}
