<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Port;

/**
 * Provides the foundational system prompt prepended to every LLM turn.
 *
 * It establishes the assistant as a coding agent, gives it the workspace
 * context and guidance on how to use the tools. Kept behind a port so the
 * wording (and any workspace/environment facts it embeds) lives in
 * Infrastructure, and the agent loop stays pure.
 */
interface SystemPrompt
{
    public function text(): string;
}
