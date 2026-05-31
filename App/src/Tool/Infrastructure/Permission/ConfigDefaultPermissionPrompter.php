<?php

declare(strict_types=1);

namespace App\Tool\Infrastructure\Permission;

use App\Tool\Domain\Model\ValueObject\PermissionAction;
use App\Tool\Domain\Model\ValueObject\PermissionRequest;
use App\Tool\Domain\Port\PermissionPrompter;

/**
 * Non-interactive prompter: resolves every `Ask` to a fixed default.
 *
 * This is the only prompter shipped in STEP-14 — there is no UI in the call
 * stack yet (tools run deep under SendMessageHandler). It defaults to DENY
 * so an un-configured one-shot run stays safe; an operator can flip the
 * default to allow via TOOL_PERMISSION_DEFAULT. Interactive CLI/TUI
 * prompters arrive with the mutating tools in STEP-15/16.
 */
final readonly class ConfigDefaultPermissionPrompter implements PermissionPrompter
{
    public function __construct(
        private PermissionAction $default = PermissionAction::Deny,
    ) {
        if (PermissionAction::Ask === $this->default) {
            throw new \InvalidArgumentException('Default prompter decision must be terminal (allow or deny), not ask.');
        }
    }

    /**
     * DI-friendly constructor: maps the TOOL_PERMISSION_DEFAULT env string to
     * a terminal action. An empty or unknown value falls back to deny.
     */
    public static function fromString(string $default): self
    {
        return new self(PermissionAction::tryFrom($default) ?? PermissionAction::Deny);
    }

    public function prompt(PermissionRequest $request, string $subject): PermissionAction
    {
        return $this->default;
    }
}
