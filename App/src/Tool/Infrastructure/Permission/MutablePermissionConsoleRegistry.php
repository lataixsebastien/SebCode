<?php

declare(strict_types=1);

namespace App\Tool\Infrastructure\Permission;

use App\Tool\Domain\Port\PermissionConsole;
use App\Tool\Domain\Port\PermissionConsoleRegistry;

/**
 * Process-wide holder for the live {@see PermissionConsole}.
 *
 * A shared (singleton) service: the CLI command attaches its console before
 * running the agent loop, the prompter reads it deep in tool execution within
 * the same synchronous call stack, then the command detaches it in a finally.
 * Starts (and resets) to a {@see NullPermissionConsole}.
 */
final class MutablePermissionConsoleRegistry implements PermissionConsoleRegistry
{
    private PermissionConsole $console;

    public function __construct(private readonly NullPermissionConsole $none)
    {
        $this->console = $none;
    }

    public function current(): PermissionConsole
    {
        return $this->console;
    }

    public function attach(PermissionConsole $console): void
    {
        $this->console = $console;
    }

    public function detach(): void
    {
        $this->console = $this->none;
    }
}
