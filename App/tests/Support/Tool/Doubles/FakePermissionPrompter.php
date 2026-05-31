<?php

declare(strict_types=1);

namespace App\Tests\Support\Tool\Doubles;

use App\Tool\Domain\Model\ValueObject\PermissionAction;
use App\Tool\Domain\Model\ValueObject\PermissionRequest;
use App\Tool\Domain\Port\PermissionPrompter;

/**
 * Scriptable prompter double. Returns a fixed decision and records every
 * (request, subject) it was asked about.
 */
final class FakePermissionPrompter implements PermissionPrompter
{
    /**
     * @var list<array{request: PermissionRequest, subject: string}>
     */
    public array $prompts = [];

    public function __construct(
        private readonly PermissionAction $decision = PermissionAction::Allow,
    ) {
    }

    public function prompt(PermissionRequest $request, string $subject): PermissionAction
    {
        $this->prompts[] = ['request' => $request, 'subject' => $subject];

        return $this->decision;
    }
}
