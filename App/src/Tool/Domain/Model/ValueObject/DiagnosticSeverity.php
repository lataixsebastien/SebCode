<?php

declare(strict_types=1);

namespace App\Tool\Domain\Model\ValueObject;

/**
 * Severity of a {@see \App\Tool\Domain\Model\Diagnostic}, mirroring the LSP
 * notion (Error / Warning are the two we surface).
 */
enum DiagnosticSeverity: string
{
    case Error = 'error';
    case Warning = 'warning';
}
