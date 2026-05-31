<?php

declare(strict_types=1);

namespace App\Tool\Domain\Model;

use App\Tool\Domain\Model\ValueObject\PermissionAction;
use App\Tool\Domain\Model\ValueObject\PermissionType;
use App\Tool\Domain\Service\WildcardMatcher;

/**
 * An ordered list of permission rules, evaluated like opencode's `evaluate()`.
 *
 * Evaluation picks the LAST rule whose type AND subject patterns both match
 * (later rules override earlier ones), and falls back to `Ask` when nothing
 * matches — exactly the semantics of
 * `permission/index.ts::evaluate` (findLast + default "ask").
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/permission/index.ts:138
 */
final readonly class PermissionRuleset
{
    /**
     * @var list<PermissionRule>
     */
    public array $rules;

    /**
     * @param list<PermissionRule> $rules
     */
    public function __construct(array $rules = [])
    {
        $this->rules = $rules;
    }

    /**
     * Resolve the action for a (type, subject) pair.
     *
     * Last matching rule wins; no match yields `Ask`.
     */
    public function evaluate(PermissionType $type, string $subject): PermissionAction
    {
        $decision = PermissionAction::Ask;

        foreach ($this->rules as $rule) {
            if (WildcardMatcher::matches($type->value, $rule->typePattern)
                && WildcardMatcher::matches($subject, $rule->subjectPattern)
            ) {
                $decision = $rule->action;
            }
        }

        return $decision;
    }
}
