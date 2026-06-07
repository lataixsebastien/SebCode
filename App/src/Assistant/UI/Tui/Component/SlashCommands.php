<?php

declare(strict_types=1);

namespace App\Assistant\UI\Tui\Component;

/**
 * The TUI's slash-command catalogue (opencode's command palette, local-only:
 * no /share, no network commands).
 */
final class SlashCommands
{
    public const string HELP = '/help';
    public const string NEW = '/new';
    public const string SESSIONS = '/sessions';
    public const string MODELS = '/models';
    public const string CLEAR = '/clear';
    public const string EXIT = '/exit';

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function items(): array
    {
        return [
            ['value' => self::HELP, 'label' => self::HELP, 'description' => 'Show help (commands & keybindings)'],
            ['value' => self::NEW, 'label' => self::NEW, 'description' => 'Start a new session'],
            ['value' => self::SESSIONS, 'label' => self::SESSIONS, 'description' => 'Switch to another session'],
            ['value' => self::MODELS, 'label' => self::MODELS, 'description' => 'Switch model (starts a new session)'],
            ['value' => self::CLEAR, 'label' => self::CLEAR, 'description' => 'Clear the transcript'],
            ['value' => self::EXIT, 'label' => self::EXIT, 'description' => 'Quit the TUI'],
        ];
    }

    /**
     * Aliases accepted directly in the composer without opening the palette.
     */
    public static function normalize(string $input): string
    {
        return match (strtolower(trim($input))) {
            '/quit', ':q', ':quit', 'exit', 'quit' => self::EXIT,
            default => trim($input),
        };
    }

    public static function isCommand(string $input): bool
    {
        $normalized = self::normalize($input);

        foreach (self::items() as $item) {
            if ($item['value'] === $normalized) {
                return true;
            }
        }

        return false;
    }
}
