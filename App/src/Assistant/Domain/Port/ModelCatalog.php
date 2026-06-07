<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Port;

use App\Assistant\Domain\Model\ValueObject\ModelName;

/**
 * Lists the models available on the local LLM platform (Ollama).
 *
 * Used by the TUI model picker (/models). Implementations must stay
 * local-only (no external network calls).
 */
interface ModelCatalog
{
    /**
     * @return list<ModelName> available models; empty when the platform is unreachable
     */
    public function availableModels(): array;
}
