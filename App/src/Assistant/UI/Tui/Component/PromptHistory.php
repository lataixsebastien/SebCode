<?php

declare(strict_types=1);

namespace App\Assistant\UI\Tui\Component;

/**
 * Prompt history ring, mirroring opencode's composer history:
 * ↑ walks back in time (saving the live draft first), ↓ walks forward and
 * eventually restores the draft. Consecutive duplicates are collapsed and
 * the ring is capped.
 */
final class PromptHistory
{
    private const int MAX_ENTRIES = 200;

    /** @var list<string> */
    private array $entries = [];
    private ?int $cursor = null;
    private string $draft = '';

    public function push(string $prompt): void
    {
        $prompt = trim($prompt);
        if ('' === $prompt) {
            return;
        }

        if ($prompt !== end($this->entries)) {
            $this->entries[] = $prompt;
            $this->entries = \array_slice($this->entries, -self::MAX_ENTRIES);
        }

        $this->resetCursor();
    }

    /**
     * Move one step back in history. `$current` is the live composer value,
     * saved as a draft the first time history is entered.
     *
     * @return string|null the recalled prompt, or null when there is no history
     */
    public function previous(string $current): ?string
    {
        if ([] === $this->entries) {
            return null;
        }

        if (null === $this->cursor) {
            $this->draft = $current;
            $this->cursor = \count($this->entries) - 1;
        } elseif ($this->cursor > 0) {
            --$this->cursor;
        }

        return $this->entries[$this->cursor];
    }

    /**
     * Move one step forward in history; walking past the newest entry
     * restores the saved draft and leaves history navigation.
     *
     * @return string|null the next prompt or restored draft, null when not navigating
     */
    public function next(): ?string
    {
        if (null === $this->cursor) {
            return null;
        }

        if ($this->cursor < \count($this->entries) - 1) {
            ++$this->cursor;

            return $this->entries[$this->cursor];
        }

        $this->cursor = null;

        return $this->draft;
    }

    public function isNavigating(): bool
    {
        return null !== $this->cursor;
    }

    public function resetCursor(): void
    {
        $this->cursor = null;
        $this->draft = '';
    }
}
