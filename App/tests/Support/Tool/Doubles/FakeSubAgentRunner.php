<?php

declare(strict_types=1);

namespace App\Tests\Support\Tool\Doubles;

use App\Tool\Domain\Port\SubAgentRunner;

/**
 * Returns a canned answer and records the (instruction, sessionId) it got.
 */
final class FakeSubAgentRunner implements SubAgentRunner
{
    /**
     * @var list<array{instruction: string, sessionId: string}>
     */
    public array $calls = [];

    public function __construct(private readonly string $answer = 'sub-agent done')
    {
    }

    public function run(string $instruction, string $sessionId): string
    {
        $this->calls[] = ['instruction' => $instruction, 'sessionId' => $sessionId];

        return $this->answer;
    }
}
