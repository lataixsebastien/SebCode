<?php

declare(strict_types=1);

namespace App\Tests\Support\Assistant\Doubles;

use App\Assistant\Domain\Port\AgentOutputStream;

/**
 * Records everything the agent loop streams, so tests can assert the live
 * sequence of text deltas and tool calls/results.
 */
final class FakeAgentOutputStream implements AgentOutputStream
{
    /** @var list<string> */
    public array $texts = [];

    /** @var list<string> */
    public array $toolCalls = [];

    /** @var list<array{name: string, output: string, isError: bool}> */
    public array $toolResults = [];

    public function assistantText(string $delta): void
    {
        $this->texts[] = $delta;
    }

    public function toolCall(string $name, array $arguments): void
    {
        $this->toolCalls[] = $name;
    }

    public function toolResult(string $name, string $output, bool $isError): void
    {
        $this->toolResults[] = ['name' => $name, 'output' => $output, 'isError' => $isError];
    }
}
