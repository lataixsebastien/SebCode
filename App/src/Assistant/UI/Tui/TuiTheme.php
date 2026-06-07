<?php

declare(strict_types=1);

namespace App\Assistant\UI\Tui;

use App\Assistant\UI\Tui\Component\StatusBarWidget;
use App\Assistant\UI\Tui\Component\StepsPanelWidget;
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\BorderPattern;
use Symfony\Component\Tui\Style\Padding;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;

/**
 * Dark theme mirroring the opencode TUI palette.
 *
 * Colors come from opencode's default theme: near-black background, peach
 * primary accent, blue secondary, muted grays for chrome, and the usual
 * success/warning/error trio.
 */
final class TuiTheme
{
    public const string BACKGROUND = '#0a0a0a';
    public const string SURFACE = '#141414';
    public const string TEXT = '#eeeeee';
    public const string MUTED = '#808080';
    public const string BORDER = '#3d3d3d';
    public const string PRIMARY = '#fab283';
    public const string SECONDARY = '#5c9cf5';
    public const string SUCCESS = '#7fd88f';
    public const string WARNING = '#f5a742';
    public const string ERROR = '#e06c75';

    public static function styleSheet(): StyleSheet
    {
        return new StyleSheet([
            ':root' => new Style(background: self::BACKGROUND, color: self::TEXT),

            // ── Transcript (immutable scrollback) ──
            '.transcript' => new Style(padding: Padding::xy(1, 0), gap: 1),
            // User entries: accent bar on the left, like opencode's `> ` block.
            '.user' => new Style(
                border: new Border(0, 0, 0, 1, BorderPattern::NORMAL, self::PRIMARY),
                padding: new Padding(0, 0, 0, 1),
                color: self::TEXT,
            ),
            '.assistant' => new Style(color: self::TEXT),
            '.tool-call' => new Style(color: self::SECONDARY),
            '.tool-result' => new Style(color: self::MUTED),
            '.tool-error' => new Style(color: self::ERROR),
            '.system' => new Style(color: self::MUTED, italic: true),
            '.error' => new Style(color: self::ERROR),
            '.splash-logo' => new Style(color: self::PRIMARY, bold: true),
            '.splash-info' => new Style(color: self::MUTED),
            '.help' => new Style(color: self::MUTED),

            // ── Footer (mutable area) ──
            '.composer' => new Style(
                border: Border::from([1], BorderPattern::ROUNDED, self::BORDER),
                padding: Padding::xy(1, 0),
            ),
            '.palette' => new Style(
                border: Border::from([1], BorderPattern::ROUNDED, self::BORDER),
                padding: Padding::xy(1, 0),
                background: self::SURFACE,
            ),
            '.palette-title' => new Style(color: self::MUTED, italic: true),
            '.permission' => new Style(
                border: Border::from([1], BorderPattern::ROUNDED, self::WARNING),
                padding: Padding::xy(1, 0),
            ),
            '.permission-title' => new Style(color: self::WARNING, bold: true),
            '.permission-body' => new Style(color: self::TEXT),
            '.permission-hint' => new Style(color: self::MUTED),

            // ── StepsPanelWidget (agent todo list, STEP-30) ──
            StepsPanelWidget::class.'::border' => new Style(color: self::BORDER),
            StepsPanelWidget::class.'::title' => new Style(color: self::PRIMARY, bold: true),
            StepsPanelWidget::class.'::counter' => new Style(color: self::MUTED),
            StepsPanelWidget::class.'::done' => new Style(color: self::SUCCESS),
            StepsPanelWidget::class.'::active' => new Style(color: self::PRIMARY, bold: true),
            StepsPanelWidget::class.'::pending' => new Style(color: self::MUTED),
            StepsPanelWidget::class.'::cancelled' => new Style(color: self::MUTED, strikethrough: true),

            // ── StatusBarWidget sub-elements ──
            // (deptrac's parser chokes on PHP 8.4 `new X()->method()` syntax,
            // so plain constructor args are used here.)
            StatusBarWidget::class.'::agent' => new Style(color: self::PRIMARY, bold: true),
            StatusBarWidget::class.'::meta' => new Style(color: self::MUTED),
            StatusBarWidget::class.'::spinner' => new Style(color: self::PRIMARY),
            StatusBarWidget::class.'::status' => new Style(color: self::TEXT),
            StatusBarWidget::class.'::hint' => new Style(color: self::MUTED),
            StatusBarWidget::class.'::usage' => new Style(color: self::MUTED),
            StatusBarWidget::class.'::warning' => new Style(color: self::WARNING, bold: true),
        ]);
    }
}
