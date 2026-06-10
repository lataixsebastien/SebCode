<?php

declare(strict_types=1);

namespace SebCode\Permission\Domain\Model\ValueObject;

enum PermissionReason: string
{
    case Allowed = 'allowed';
    case WorkspaceViolation = 'workspace_violation';
    case WriteRequiresApproval = 'write_requires_approval';
    case CommandDenied = 'command_denied';
    case CommandRequiresApproval = 'command_requires_approval';
    case NetworkDenied = 'network_denied';
}
