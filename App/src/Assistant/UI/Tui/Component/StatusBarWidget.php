<?php

declare(strict_types=1);

namespace App\Assistant\UI\Tui\Component;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\AbstractWidget;

/**
 * The footer's bottom two rows, opencode-style:
 *
 *   build · qwen2.5:3b                                    ses_0123456789ab
 *   ⠹ working… 3.2s  esc interrupt                    ↑12.4k ↓1.2k tokens
 *
 * Row 1 ("meta row"): agent + model on the left, session id on the right.
 * Row 2 ("status row"): spinner + state + contextual hints on the left,
 * cumulated token usage on the right.
 *
 * The spinner is advanced manually via {@see tick()} because the agent loop
 * blocks the Revolt loop during a turn (STEP-27 mechanics) — scheduled ticks
 * cannot fire, so the streaming sink drives the animation on each delta.
 */
final class StatusBarWidget extends AbstractWidget
{
    private const array FRAMES = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
    private const float FRAME_INTERVAL = 0.08;

    private string $agent = 'build';
    private string $model = '';
    private string $sessionId = '';

    private bool $working = false;
    private float $startedAt = 0.0;
    private int $frame = 0;
    private float $lastFrameAt = 0.0;

    private int $promptTokens = 0;
    private int $completionTokens = 0;

    private bool $exitArmed = false;
    private ?string $notice = null;

    public function setIdentity(string $agent, string $model, string $sessionId): void
    {
        $this->agent = $agent;
        $this->model = $model;
        $this->sessionId = $sessionId;
        $this->invalidate();
    }

    public function startWorking(): void
    {
        $this->working = true;
        $this->startedAt = microtime(true);
        $this->frame = 0;
        $this->lastFrameAt = 0.0;
        $this->notice = null;
        $this->invalidate();
    }

    public function stopWorking(): void
    {
        $this->working = false;
        $this->invalidate();
    }

    public function isWorking(): bool
    {
        return $this->working;
    }

    /**
     * Advance the spinner frame if enough time has passed. Called by the
     * streaming sink between deltas (the Revolt loop is blocked meanwhile).
     */
    public function tick(): void
    {
        if (!$this->working) {
            return;
        }

        $now = microtime(true);
        if ($now - $this->lastFrameAt >= self::FRAME_INTERVAL) {
            $this->frame = ($this->frame + 1) % \count(self::FRAMES);
            $this->lastFrameAt = $now;
            $this->invalidate();
        }
    }

    public function addUsage(?int $promptTokens, ?int $completionTokens): void
    {
        $this->promptTokens += $promptTokens ?? 0;
        $this->completionTokens += $completionTokens ?? 0;
        $this->invalidate();
    }

    /** Show the "press ctrl+c again to exit" warning in place of the hints. */
    public function armExit(bool $armed): void
    {
        if ($this->exitArmed !== $armed) {
            $this->exitArmed = $armed;
            $this->invalidate();
        }
    }

    /** One-shot informational message (e.g. "interrupted"), cleared on next turn. */
    public function setNotice(?string $notice): void
    {
        if ($this->notice !== $notice) {
            $this->notice = $notice;
            $this->invalidate();
        }
    }

    public function render(RenderContext $context): array
    {
        $columns = $context->getColumns();

        $metaLeft = $this->applyElement('agent', $this->agent)
            .$this->applyElement('meta', ' · '.$this->model);
        $metaRight = $this->applyElement('meta', $this->sessionId);

        return [
            $this->row($metaLeft, $metaRight, $columns),
            $this->row($this->statusLeft(), $this->usageRight(), $columns),
        ];
    }

    private function statusLeft(): string
    {
        if ($this->exitArmed) {
            return $this->applyElement('warning', 'ctrl+c again to exit');
        }

        if ($this->working) {
            $elapsed = max(0.0, microtime(true) - $this->startedAt);

            return $this->applyElement('spinner', self::FRAMES[$this->frame])
                .' '.$this->applyElement('status', \sprintf('working… %.1fs', $elapsed))
                .'  '.$this->applyElement('hint', 'esc interrupt');
        }

        $left = '';
        if (null !== $this->notice && '' !== $this->notice) {
            $left = $this->applyElement('status', $this->notice).'  ';
        }

        return $left.$this->applyElement('hint', 'enter send · / commands · ↑↓ history · ctrl+c quit');
    }

    private function usageRight(): string
    {
        if (0 === $this->promptTokens && 0 === $this->completionTokens) {
            return '';
        }

        return $this->applyElement('usage', \sprintf(
            '↑%s ↓%s tokens',
            $this->formatTokens($this->promptTokens),
            $this->formatTokens($this->completionTokens),
        ));
    }

    private function formatTokens(int $tokens): string
    {
        return $tokens >= 1000 ? \sprintf('%.1fk', $tokens / 1000) : (string) $tokens;
    }

    private function row(string $left, string $right, int $columns): string
    {
        $leftWidth = AnsiUtils::visibleWidth($left);
        $rightWidth = AnsiUtils::visibleWidth($right);

        if ($leftWidth + $rightWidth + 1 > $columns) {
            return AnsiUtils::truncateToWidth($left, $columns, '…');
        }

        return $left.str_repeat(' ', $columns - $leftWidth - $rightWidth).$right;
    }
}
