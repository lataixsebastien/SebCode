<?php

declare(strict_types=1);

namespace App\Tests\Support\Assistant\Doubles;

use App\Assistant\Domain\Port\SystemPrompt;

/**
 * Returns a fixed system prompt string, so tests can assert it is prepended.
 */
final readonly class FixedSystemPrompt implements SystemPrompt
{
    public function __construct(private string $text = 'SYSTEM PROMPT')
    {
    }

    public function text(): string
    {
        return $this->text;
    }
}
