<?php

declare(strict_types=1);

namespace App\Assistant\UI\Tui;

use App\Tool\Domain\Model\ValueObject\PermissionChoice;
use App\Tool\Domain\Model\ValueObject\PermissionRequest;
use App\Tool\Domain\Port\PermissionConsole;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * TUI adapter of {@see PermissionConsole}: shows the request as a transcript
 * widget and blocks for a single keypress (o / a / r).
 *
 * Why a direct terminal read rather than the widget event system: the agent
 * loop runs synchronously inside an `EventLoop::queue()` closure and the LLM
 * call blocks, so the Revolt loop is frozen for the whole turn — its stdin
 * watcher cannot deliver keys to a widget meanwhile. But precisely because the
 * loop is frozen, reading stdin directly here is safe: nothing else is
 * consuming it. We force a synchronous render to paint the prompt, then
 * `stream_select` + `fread` the answer. (See ADR-0008 / STEP-18.)
 */
final class TuiPermissionConsole implements PermissionConsole
{
    /** @var resource */
    private $stdin;
    private bool $active = false;

    /**
     * @param resource|null $stdin defaults to STDIN; injectable for tests
     */
    public function __construct(
        private readonly Tui $tui,
        private readonly ContainerWidget $transcript,
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
        $text = \sprintf('⚠ Permission requested (%s)', $request->type->value);
        $description = $request->metadata['description'] ?? null;
        if (\is_string($description) && '' !== $description) {
            $text .= "\n".$description;
        }
        $text .= "\n".$subject."\n[o] allow once    [a] allow always (project)    [r] reject";

        $prompt = $this->paint($text);
        $choice = $this->readChoice();
        $this->transcript->remove($prompt);
        $this->paint(\sprintf('⚠ %s (%s) → %s', $subject, $request->type->value, $this->label($choice)));

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

    private function paint(string $text): TextWidget
    {
        $widget = (new TextWidget($text))->addStyleClass('permission');
        $this->transcript->add($widget);
        $this->tui->requestRender(true);
        $this->tui->processRender();

        return $widget;
    }

    private function readChoice(): PermissionChoice
    {
        while (true) {
            $read = [$this->stdin];
            $write = null;
            $except = null;
            if (false === @stream_select($read, $write, $except, null)) {
                return PermissionChoice::Reject;
            }
            $data = fread($this->stdin, 64);
            if (false === $data || '' === $data) {
                continue;
            }
            $choice = self::mapKeys($data);
            if (null !== $choice) {
                return $choice;
            }
        }
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
