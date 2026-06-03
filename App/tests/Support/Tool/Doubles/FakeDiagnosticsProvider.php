<?php

declare(strict_types=1);

namespace App\Tests\Support\Tool\Doubles;

use App\Tool\Domain\Model\Diagnostic;
use App\Tool\Domain\Port\DiagnosticsProvider;

/**
 * Returns a canned list of diagnostics and records the path it was asked about.
 */
final class FakeDiagnosticsProvider implements DiagnosticsProvider
{
    /** @var list<string> */
    public array $paths = [];

    /**
     * @param list<Diagnostic> $diagnostics
     */
    public function __construct(private readonly array $diagnostics = [])
    {
    }

    public function diagnostics(string $absolutePath): array
    {
        $this->paths[] = $absolutePath;

        return $this->diagnostics;
    }
}
