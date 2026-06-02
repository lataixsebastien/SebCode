<?php

declare(strict_types=1);

namespace App\Tests\Support\Tool\Doubles;

use App\Tool\Domain\Exception\PermissionDenied;
use App\Tool\Domain\Model\ValueObject\PermissionRequest;
use App\Tool\Domain\Port\PermissionGate;

/**
 * Records every PermissionRequest it receives. Allows by default; constructed
 * with allow=false to make ensure() throw PermissionDenied on the first
 * pattern (simulating a deny/refused decision).
 */
final class FakePermissionGate implements PermissionGate
{
    /**
     * @var list<PermissionRequest>
     */
    public array $requests = [];

    public function __construct(private readonly bool $allow = true)
    {
    }

    public function ensure(PermissionRequest $request): void
    {
        $this->requests[] = $request;
        if (!$this->allow) {
            throw PermissionDenied::for($request->type, $request->patterns[0]);
        }
    }
}
