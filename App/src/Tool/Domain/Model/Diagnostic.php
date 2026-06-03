<?php

declare(strict_types=1);

namespace App\Tool\Domain\Model;

use App\Tool\Domain\Model\ValueObject\DiagnosticSeverity;

/**
 * One problem reported for a file: a severity, a 1-based line (0 if unknown),
 * a message and the tool that produced it ("php", "phpstan", …).
 */
final readonly class Diagnostic
{
    public function __construct(
        public DiagnosticSeverity $severity,
        public int $line,
        public string $message,
        public string $source,
    ) {
    }
}
