<?php

declare(strict_types=1);

namespace App\Tool\Domain\Port;

use App\Tool\Domain\Model\Diagnostic;

/**
 * Reports diagnostics (errors/warnings) for a source file — the capability the
 * `lsp` tool exposes.
 *
 * Abstracted so the tool doesn't care how diagnostics are obtained: today a
 * PHP-native backend (php -l + phpstan), tomorrow possibly a real language
 * server, without changing the tool.
 */
interface DiagnosticsProvider
{
    /**
     * @param string $absolutePath an existing file inside the workspace
     *
     * @return list<Diagnostic>
     */
    public function diagnostics(string $absolutePath): array;
}
