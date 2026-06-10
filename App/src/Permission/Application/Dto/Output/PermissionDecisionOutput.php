<?php

declare(strict_types=1);

namespace SebCode\Permission\Application\Dto\Output;

use SebCode\Permission\Domain\Model\PermissionDecision;
use SebCode\Permission\Domain\Model\ValueObject\PermissionReason;

final readonly class PermissionDecisionOutput
{
    public function __construct(
        public bool $allowed,
        public bool $requiresApproval,
        public PermissionReason $reason,
        public string $message,
    ) {
    }

    public static function fromDomain(PermissionDecision $decision): self
    {
        return new self($decision->allowed, $decision->requiresApproval, $decision->reason, $decision->message);
    }

    public function isDenied(): bool
    {
        return !$this->allowed && !$this->requiresApproval;
    }
}
