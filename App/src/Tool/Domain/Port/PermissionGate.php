<?php

declare(strict_types=1);

namespace App\Tool\Domain\Port;

use App\Tool\Domain\Exception\PermissionDenied;
use App\Tool\Domain\Model\ValueObject\PermissionRequest;

/**
 * Authorizes a guarded tool action before it happens.
 *
 * The single entry point tools call (the SebCode equivalent of opencode's
 * `ctx.ask(...)`). It evaluates the configured ruleset for every pattern in
 * the request, delegates undecided (`Ask`) cases to a {@see PermissionPrompter},
 * and either returns silently (all allowed) or throws.
 */
interface PermissionGate
{
    /**
     * @throws PermissionDenied if any requested pattern is refused
     */
    public function ensure(PermissionRequest $request): void;
}
