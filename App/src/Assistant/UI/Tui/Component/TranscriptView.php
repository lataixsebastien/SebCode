<?php

declare(strict_types=1);

namespace App\Assistant\UI\Tui\Component;

use App\Assistant\Domain\Model\Message;
use App\Assistant\Domain\Model\ValueObject\MessagePayloadKind;
use App\Assistant\Domain\Model\ValueObject\MessageRole;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\MarkdownWidget;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * The immutable scrollback (opencode's transcript): typed append-only entries.
 *
 * User prompts get the accent left-bar, assistant replies render as markdown,
 * tool calls/results are formatted per tool by {@see ToolEntryFormatter}.
 * Spacing between entries comes from the container's `gap` style.
 */
final class TranscriptView
{
    private const string LOGO = <<<'TXT'
        █▀▀ █▀▀ █▀▄ █▀▀ █▀█ █▀▄ █▀▀
        ▀▀█ █▀▀ █▀▄ █   █ █ █ █ █▀▀
        ▀▀▀ ▀▀▀ ▀▀▀ ▀▀▀ ▀▀▀ ▀▀▀ ▀▀▀
        TXT;

    public function __construct(
        private readonly ContainerWidget $container,
        private readonly ToolEntryFormatter $formatter = new ToolEntryFormatter(),
    ) {
    }

    public function splash(string $model, string $sessionId, string $title): void
    {
        $this->container->add((new TextWidget(self::LOGO))->addStyleClass('splash-logo'));
        $this->container->add((new TextWidget(\sprintf(
            "%s · %s\n%s\ntype your prompt, /help for commands",
            $title,
            $model,
            $sessionId,
        )))->addStyleClass('splash-info'));
    }

    public function user(string $text): void
    {
        $this->container->add((new TextWidget($text))->addStyleClass('user'));
    }

    /**
     * Open a fresh assistant entry for live streaming; the caller updates it
     * with {@see MarkdownWidget::setText()} as deltas arrive.
     */
    public function assistantStart(): MarkdownWidget
    {
        $widget = (new MarkdownWidget(''))->addStyleClass('assistant');
        $this->container->add($widget);

        return $widget;
    }

    public function assistantMarkdown(string $markdown): void
    {
        if ('' === trim($markdown)) {
            return;
        }

        $this->container->add((new MarkdownWidget($markdown))->addStyleClass('assistant'));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function toolCall(string $name, array $arguments): void
    {
        $this->container->add(
            (new TextWidget($this->formatter->callLine($name, $arguments)))->addStyleClass('tool-call'),
        );
    }

    public function toolResult(string $name, string $output, bool $isError): void
    {
        $this->container->add(
            (new TextWidget($this->formatter->resultText($name, $output, $isError)))
                ->addStyleClass($isError ? 'tool-error' : 'tool-result'),
        );
    }

    public function system(string $text): void
    {
        $this->container->add((new TextWidget($text))->addStyleClass('system'));
    }

    public function error(string $text): void
    {
        $this->container->add((new TextWidget('✗ '.$text))->addStyleClass('error'));
    }

    public function help(): void
    {
        $lines = ['Commands'];
        foreach (SlashCommands::items() as $item) {
            $lines[] = \sprintf('  %-11s %s', $item['value'], $item['description']);
        }
        $lines[] = '';
        $lines[] = 'Keybindings';
        $lines[] = '  enter        send prompt';
        $lines[] = '  ↑ / ↓        prompt history (or palette navigation)';
        $lines[] = '  esc          clear input / close palette / interrupt generation';
        $lines[] = '  ctrl+c (×2)  quit';
        $lines[] = '  pgup / pgdn  scroll transcript';

        $this->container->add((new TextWidget(implode("\n", $lines)))->addStyleClass('help'));
    }

    public function clear(): void
    {
        $this->container->clear();
    }

    /** Hydrate one persisted message into the transcript (session resume). */
    public function message(Message $message): void
    {
        $payload = $message->payload;

        if (null !== $payload && MessagePayloadKind::ToolCall === $payload->kind) {
            foreach ($payload->toolCalls as $call) {
                $this->toolCall($call->name, $call->arguments);
            }

            return;
        }

        if (null !== $payload && MessagePayloadKind::ToolResult === $payload->kind) {
            $this->toolResult(
                $payload->toolName ?? '?',
                (string) ($payload->toolOutput ?? ''),
                $payload->isError,
            );

            return;
        }

        match ($message->role) {
            MessageRole::User => $this->user($message->content->text),
            MessageRole::Assistant => $this->assistantMarkdown($message->content->text),
            MessageRole::System, MessageRole::Tool => $this->system($message->content->text),
        };
    }
}
