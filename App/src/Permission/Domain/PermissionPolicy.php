<?php

declare(strict_types=1);

namespace SebCode\Permission\Domain;

use SebCode\Permission\Domain\Model\PermissionContext;
use SebCode\Permission\Domain\Model\PermissionDecision;
use SebCode\Permission\Domain\Model\PermissionRequest;
use SebCode\Permission\Domain\Model\ValueObject\PermissionReason;
use SebCode\Permission\Domain\Model\ValueObject\PermissionRequestType;
use SebCode\Permission\Domain\Port\ApprovalStore;
use SebCode\Permission\Domain\Port\NetworkAccessPolicy;
use SebCode\Permission\Domain\Port\WorkspaceAccessPolicy;

final class PermissionPolicy
{
    public function __construct(
        private readonly WorkspaceAccessPolicy $workspaceAccessPolicy,
        private readonly NetworkAccessPolicy $networkAccessPolicy,
        private readonly ApprovalStore $approvalStore,
        private readonly DecisionEngine $decisionEngine,
    ) {
    }

    public function decide(PermissionRequest $request, PermissionContext $context): PermissionDecision
    {
        return match ($request->type) {
            PermissionRequestType::ReadFile => $this->decideRead($request, $context),
            PermissionRequestType::WriteFile => $this->decideWrite($request, $context),
            PermissionRequestType::RunCommand => $this->decideCommand($request, $context),
            PermissionRequestType::Network => $this->decideNetwork($request),
        };
    }

    private function decideRead(PermissionRequest $request, PermissionContext $context): PermissionDecision
    {
        try {
            $this->workspaceAccessPolicy->assertPathAllowed($context->workspaceRoot, $request->path());
        } catch (\Throwable $violation) {
            return PermissionDecision::denied(PermissionReason::WorkspaceViolation, $violation->getMessage());
        }

        return PermissionDecision::allowed('Read is allowed.');
    }

    private function decideWrite(PermissionRequest $request, PermissionContext $context): PermissionDecision
    {
        try {
            $this->workspaceAccessPolicy->assertPathAllowed($context->workspaceRoot, $request->path());
        } catch (\Throwable $violation) {
            return PermissionDecision::denied(PermissionReason::WorkspaceViolation, $violation->getMessage());
        }

        if ($this->approvalStore->hasApproval($request, $context)) {
            return PermissionDecision::allowed('Write was pre-approved.');
        }

        return PermissionDecision::approvalRequired(
            PermissionReason::WriteRequiresApproval,
            'Write operations require approval.',
        );
    }

    private function decideCommand(PermissionRequest $request, PermissionContext $context): PermissionDecision
    {
        if ($this->approvalStore->hasApproval($request, $context)) {
            return PermissionDecision::allowed('Command was pre-approved.');
        }

        return $this->decisionEngine->decideCommand($request);
    }

    private function decideNetwork(PermissionRequest $request): PermissionDecision
    {
        try {
            $this->networkAccessPolicy->assertUrlAllowed($request->url());
        } catch (\Throwable $violation) {
            return PermissionDecision::denied(PermissionReason::NetworkDenied, $violation->getMessage());
        }

        return PermissionDecision::allowed('Local network access is allowed.');
    }
}
