<?php

declare(strict_types=1);

namespace SebCode\Permission\Domain\Model;

use SebCode\Permission\Domain\Model\ValueObject\PermissionReason;

final readonly class PermissionDecision
{
    private function __construct(
        public bool $allowed,
        public bool $requiresApproval,
        public PermissionReason $reason,
        public string $message,
    ) {
    }

    public static function allowed(string $message = 'Allowed.'): self
    {
        return new self(true, false, PermissionReason::Allowed, $message);
    }

    public static function denied(PermissionReason $reason, string $message): self
    {
        return new self(false, false, $reason, $message);
    }

    public static function approvalRequired(PermissionReason $reason, string $message): self
    {
        return new self(false, true, $reason, $message);
    }

    public function isDenied(): bool
    {
        return !$this->allowed && !$this->requiresApproval;
    }
}
