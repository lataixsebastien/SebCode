<?php

declare(strict_types=1);

namespace App\Assistant\UI\Tui;

use App\Assistant\UI\Tui\Component\TranscriptView;
use App\Tool\Domain\Model\ValueObject\PermissionChoice;
use App\Tool\Domain\Model\ValueObject\PermissionRequest;
use App\Tool\Domain\Port\PermissionConsole;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * TUI adapter of {@see PermissionConsole}: opencode-style permission dialog
 * in the footer — three buttons (Allow once / Always / Reject) navigated with
 * ←/→/Tab and confirmed with Enter; o/a/r and 1/2/3 still answer directly,
 * Esc rejects.
 *
 * Why a direct terminal read rather than the widget event system: the agent
 * loop runs synchronously inside an `EventLoop::queue()` closure and the LLM
 * call blocks, so the Revolt loop is frozen for the whole turn — its stdin
 * watcher cannot deliver keys to a widget meanwhile. But precisely because the
 * loop is frozen, reading stdin directly here is safe: nothing else is
 * consuming it. We force a synchronous render to paint the dialog, then
 * `stream_select` + `fread` the answer. (See ADR-0008 / STEP-18.)
 */
final class TuiPermissionConsole implements PermissionConsole
{
    private const array CHOICES = [
        PermissionChoice::AllowOnce,
        PermissionChoice::AllowAlways,
        PermissionChoice::Reject,
    ];

    /** @var resource */
    private $stdin;
    private bool $active = false;

    /**
     * @param resource|null $stdin defaults to STDIN; injectable for tests
     */
    public function __construct(
        private readonly Tui $tui,
        private readonly ContainerWidget $dialogSlot,
        private readonly TranscriptView $transcript,
        $stdin = null,
    ) {
        $this->stdin = \is_resource($stdin) ? $stdin : \STDIN;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function isActive(): bool
    {
        return $this->active && stream_isatty($this->stdin);
    }

    public function confirm(PermissionRequest $request, string $subject): PermissionChoice
    {
        $title = (new TextWidget(\sprintf('Permission — %s', $request->type->value)))
            ->addStyleClass('permission-title');

        $body = $subject;
        $description = $request->metadata['description'] ?? null;
        if (\is_string($description) && '' !== $description) {
            $body = $description."\n".$subject;
        }

        $buttons = new TextWidget('');
        $panel = (new ContainerWidget())->addStyleClass('permission');
        $panel->add($title);
        $panel->add((new TextWidget($body))->addStyleClass('permission-body'));
        $panel->add($buttons);
        $panel->add((new TextWidget('←/→ select · enter confirm · esc reject · o/a/r direct'))
            ->addStyleClass('permission-hint'));

        $this->dialogSlot->add($panel);

        $selected = 0;
        $choice = null;
        while (null === $choice) {
            $buttons->setText($this->buttonsLine($selected));
            $this->render();
            $choice = $this->readChoice($selected);
        }

        $this->dialogSlot->remove($panel);
        $this->transcript->system(\sprintf('permission %s (%s) → %s', $subject, $request->type->value, $this->label($choice)));
        $this->render();

        return $choice;
    }

    /**
     * Map a chunk of raw key bytes to a decision, or null if none recognised.
     * Escape and Ctrl-C (raw bytes under stty raw) count as reject.
     */
    public static function mapKeys(string $data): ?PermissionChoice
    {
        foreach (str_split($data) as $byte) {
            $choice = match (strtolower($byte)) {
                'o', '1' => PermissionChoice::AllowOnce,
                'a', '2' => PermissionChoice::AllowAlways,
                'r', '3', "\x1b", "\x03" => PermissionChoice::Reject,
                default => null,
            };
            if (null !== $choice) {
                return $choice;
            }
        }

        return null;
    }

    /**
     * Map a chunk of raw key bytes to a button-navigation action.
     *
     * @return 'left'|'right'|'enter'|null
     */
    public static function mapNavigation(string $data): ?string
    {
        return match (true) {
            str_contains($data, "\x1b[D") || str_contains($data, "\x1bOD") => 'left',
            str_contains($data, "\x1b[C") || str_contains($data, "\x1bOC"), "\t" === $data => 'right',
            str_contains($data, "\r") || str_contains($data, "\n") => 'enter',
            default => null,
        };
    }

    /**
     * Read one key chunk and resolve it; navigation updates `$selected`
     * (by reference) and returns null so the dialog loop repaints.
     */
    private function readChoice(int &$selected): ?PermissionChoice
    {
        $read = [$this->stdin];
        $write = null;
        $except = null;
        if (false === @stream_select($read, $write, $except, null)) {
            return PermissionChoice::Reject;
        }

        $data = fread($this->stdin, 64);
        if (false === $data || '' === $data) {
            return null;
        }

        switch (self::mapNavigation($data)) {
            case 'left':
                $selected = (0 === $selected ? \count(self::CHOICES) : $selected) - 1;

                return null;
            case 'right':
                $selected = ($selected + 1) % \count(self::CHOICES);

                return null;
            case 'enter':
                return self::CHOICES[$selected];
        }

        return self::mapKeys($data);
    }

    private function buttonsLine(int $selected): string
    {
        $labels = ['Allow once', 'Always (project)', 'Reject'];
        $parts = [];
        foreach ($labels as $i => $label) {
            $parts[] = $i === $selected
                ? "\x1b[7m ".$label." \x1b[27m"
                : ' '.$label.' ';
        }

        return implode('  ', $parts);
    }

    private function render(): void
    {
        $this->tui->requestRender(true);
        $this->tui->processRender();
    }

    private function label(PermissionChoice $choice): string
    {
        return match ($choice) {
            PermissionChoice::AllowOnce => 'allowed once',
            PermissionChoice::AllowAlways => 'allowed (project)',
            PermissionChoice::Reject => 'rejected',
        };
    }
}
