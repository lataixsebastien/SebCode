<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\Infrastructure\Prompt;

use App\Assistant\Infrastructure\Prompt\SebCodeSystemPrompt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SebCodeSystemPrompt::class)]
final class SebCodeSystemPromptTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'sebcode_prompt_'.bin2hex(random_bytes(4));
        mkdir($base, 0o777, true);
        $this->root = (string) realpath($base);
    }

    protected function tearDown(): void
    {
        @unlink($this->root.'/AGENTS.md');
        @rmdir($this->root);
    }

    public function testBasePromptStatesRoleWorkspaceAndTools(): void
    {
        $text = (new SebCodeSystemPrompt($this->root))->text();

        self::assertStringContainsString('SebCode', $text);
        self::assertStringContainsString('Workspace root: '.$this->root, $text);
        self::assertStringContainsString('apply_patch', $text);
        self::assertStringContainsString('todowrite', $text);
    }

    public function testOmitsInstructionsSectionWhenNoAgentsFile(): void
    {
        $text = (new SebCodeSystemPrompt($this->root))->text();

        self::assertStringNotContainsString('Project-specific instructions', $text);
    }

    public function testAppendsAgentsFileWhenPresent(): void
    {
        file_put_contents($this->root.'/AGENTS.md', "Use tabs, not spaces.\nAlways run composer qa.");

        $text = (new SebCodeSystemPrompt($this->root))->text();

        self::assertStringContainsString('Project-specific instructions (from AGENTS.md)', $text);
        self::assertStringContainsString('Use tabs, not spaces.', $text);
        self::assertStringContainsString('Always run composer qa.', $text);
    }

    public function testTruncatesAnOversizedAgentsFile(): void
    {
        file_put_contents($this->root.'/AGENTS.md', str_repeat('x', 9000));

        $text = (new SebCodeSystemPrompt($this->root))->text();

        self::assertStringContainsString('…(truncated)', $text);
    }
}
