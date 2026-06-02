<?php

declare(strict_types=1);

namespace App\Tool\Domain\Port;

use App\Tool\Domain\Model\ValueObject\PermissionChoice;
use App\Tool\Domain\Model\ValueObject\PermissionRequest;

/**
 * The interaction channel that asks a human to resolve an `Ask` permission.
 *
 * Symfony-free on purpose: the CLI provides a Symfony-Console adapter, a TUI
 * would provide a widget-based one, and headless runs leave it inactive. The
 * prompter only calls {@see confirm()} when {@see isActive()} is true.
 */
interface PermissionConsole
{
    /**
     * True when a real UI is attached and can block for an answer.
     *
     * False in one-shot/headless/non-interactive runs — the prompter then
     * falls back to its configured default instead of calling confirm().
     */
    public function isActive(): bool;

    /**
     * Render the request for one concrete subject and block for the answer.
     */
    public function confirm(PermissionRequest $request, string $subject): PermissionChoice;
}
