<?php

declare(strict_types=1);

namespace App\Tests\Support\Tool\Doubles;

use App\Tool\Domain\Model\ValueObject\PermissionChoice;
use App\Tool\Domain\Model\ValueObject\PermissionRequest;
use App\Tool\Domain\Port\PermissionConsole;

/**
 * Scriptable console double: a fixed active flag and answer, recording every
 * (request, subject) it was asked to confirm.
 */
final class FakePermissionConsole implements PermissionConsole
{
    /**
     * @var list<array{request: PermissionRequest, subject: string}>
     */
    public array $confirmations = [];

    public function __construct(
        private readonly bool $active = true,
        private readonly PermissionChoice $choice = PermissionChoice::AllowOnce,
    ) {
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function confirm(PermissionRequest $request, string $subject): PermissionChoice
    {
        $this->confirmations[] = ['request' => $request, 'subject' => $subject];

        return $this->choice;
    }
}
