<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Prompt;

use App\Assistant\Domain\Port\SystemPrompt;

/**
 * The SebCode coding-agent system prompt.
 *
 * Adapted (concise) from opencode's agent prompt for small local models:
 * states the role, embeds the workspace root + OS, and gives terse rules on
 * tool usage so glob/read/grep/write/edit/apply_patch/bash/todowrite are used
 * well. If the project ships an AGENTS.md, its content is appended so the agent
 * follows the project's own conventions (faithful to opencode). English on
 * purpose — more reliable for the models than French.
 */
final readonly class SebCodeSystemPrompt implements SystemPrompt
{
    private const string INSTRUCTIONS_FILE = 'AGENTS.md';
    private const int MAX_INSTRUCTIONS_BYTES = 8192;

    public function __construct(private string $projectRoot)
    {
    }

    public function text(): string
    {
        $base = <<<PROMPT
            You are SebCode, an autonomous coding agent working inside a developer's project.

            Workspace root: {$this->projectRoot}
            OS: {$this->os()}

            You can call tools to inspect and change the project:
            - glob — find files by pattern. grep — search file contents by regex. read — read a file.
            - write — create/overwrite a file. edit — replace an exact string in a file.
            - apply_patch — apply a multi-file patch (add/update/delete/move) in one call.
            - bash — run a shell command (git, composer, run tests, …).
            - todowrite — keep a task list for multi-step work.

            Rules:
            - Always read a file before editing it; keep the file's existing style and conventions.
            - Prefer edit/apply_patch over rewriting whole files; keep changes minimal and focused.
            - Use bash for terminal tasks only — never to read, search or edit files (use the dedicated tools).
            - For work of 3+ steps, use todowrite to plan and track progress; keep exactly one task in_progress.
            - Don't invent paths — discover them with glob/grep/read. Paths are relative to the workspace root.
            - Verify your work (e.g. run the relevant tests) before saying a task is done.
            - Be concise. When the task is complete, stop calling tools and give a short final answer.
            PROMPT;

        $instructions = $this->projectInstructions();
        if ('' === $instructions) {
            return $base;
        }

        return $base."\n\n## Project-specific instructions (from AGENTS.md)\n\n".$instructions;
    }

    /**
     * The project's AGENTS.md content (trimmed, size-capped), or '' if absent.
     */
    private function projectInstructions(): string
    {
        $path = $this->projectRoot.\DIRECTORY_SEPARATOR.self::INSTRUCTIONS_FILE;
        if (!is_file($path)) {
            return '';
        }

        $content = trim((string) file_get_contents($path));
        if (\strlen($content) > self::MAX_INSTRUCTIONS_BYTES) {
            $content = substr($content, 0, self::MAX_INSTRUCTIONS_BYTES)."\n…(truncated)";
        }

        return $content;
    }

    private function os(): string
    {
        return \PHP_OS_FAMILY;
    }
}
