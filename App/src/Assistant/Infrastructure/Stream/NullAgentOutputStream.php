<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Stream;

use App\Assistant\Domain\Port\AgentOutputStream;

/**
 * The "no UI attached" sink: discards everything. Default registry state, so
 * headless/one-shot/test runs behave exactly as before live streaming existed.
 */
final class NullAgentOutputStream implements AgentOutputStream
{
    public function assistantText(string $delta): void
    {
    }

    public function toolCall(string $name, array $arguments): void
    {
    }

    public function toolResult(string $name, string $output, bool $isError): void
    {
    }
}
