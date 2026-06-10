<?php

declare(strict_types=1);

namespace SebCode\Permission\Domain;

use SebCode\Permission\Domain\Model\PermissionDecision;
use SebCode\Permission\Domain\Model\PermissionRequest;
use SebCode\Permission\Domain\Model\ValueObject\PermissionReason;

final class DecisionEngine
{
    /**
     * @var list<string>
     */
    private const DENIED_COMMAND_PATTERNS = [
        '/\b(curl|wget|irm|iwr|invoke-webrequest|nc|ncat|telnet|ssh|scp|ftp)\b/i',
        '/\brm\s+-rf\b/i',
        '/\bdel\s+\/[fq]\b/i',
        '/\bformat\b/i',
    ];

    /**
     * @var list<string>
     */
    private const REVIEW_COMMAND_PATTERNS = [
        '/(^|\s)(vendor\/bin\/)?phpunit(\s|$)/i',
        '/(^|\s)composer\s+(test|qa|stan|cs)(\s|$)/i',
        '/(^|\s)php\s+bin\/(console|sebcode)(\s|$)/i',
        '/(^|\s)git\s+(status|diff|show|branch)(\s|$)/i',
    ];

    public function decideCommand(PermissionRequest $request): PermissionDecision
    {
        $command = $request->command();
        foreach (self::DENIED_COMMAND_PATTERNS as $pattern) {
            if (1 === preg_match($pattern, $command)) {
                return PermissionDecision::denied(
                    PermissionReason::CommandDenied,
                    'Command matches a denied shell pattern.',
                );
            }
        }

        foreach (self::REVIEW_COMMAND_PATTERNS as $pattern) {
            if (1 === preg_match($pattern, $command)) {
                return PermissionDecision::approvalRequired(
                    PermissionReason::CommandRequiresApproval,
                    'Command requires approval before execution.',
                );
            }
        }

        return PermissionDecision::approvalRequired(
            PermissionReason::CommandRequiresApproval,
            'Unclassified command requires approval before execution.',
        );
    }
}
