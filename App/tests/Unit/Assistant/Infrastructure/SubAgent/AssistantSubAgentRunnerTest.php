<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\Infrastructure\SubAgent;

use App\Assistant\Domain\Model\ValueObject\ToolAdvertisement;
use App\Assistant\Domain\Model\ValueObject\ToolCallRequest;
use App\Assistant\Infrastructure\SubAgent\AssistantSubAgentRunner;
use App\Tests\Support\Assistant\Doubles\FixedClock;
use App\Tests\Support\Assistant\Doubles\FixedSystemPrompt;
use App\Tests\Support\Assistant\Doubles\RecordingToolGateway;
use App\Tests\Support\Assistant\Doubles\ScriptedLlm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AssistantSubAgentRunner::class)]
final class AssistantSubAgentRunnerTest extends TestCase
{
    private ScriptedLlm $llm;
    private RecordingToolGateway $gateway;
    private AssistantSubAgentRunner $runner;

    protected function setUp(): void
    {
        $this->llm = new ScriptedLlm();
        $this->gateway = new RecordingToolGateway();
        $this->runner = new AssistantSubAgentRunner(
            $this->llm,
            $this->gateway,
            new FixedSystemPrompt('SYS'),
            new FixedClock('2026-06-03T12:00:00+00:00'),
            'qwen2.5:3b',
        );
    }

    public function testReturnsFinalTextWhenNoTools(): void
    {
        $this->llm->scriptReply('the answer');

        self::assertSame('the answer', $this->runner->run('do it', 'ses_parent'));

        // System prompt leads, then the instruction.
        $conversation = $this->llm->calls()[0]['conversation'];
        self::assertSame('SYS', $conversation[0]->content->text);
        self::assertSame('do it', $conversation[1]->content->text);
    }

    public function testExecutesToolsScopedToTheParentSessionThenReturnsText(): void
    {
        $this->gateway->advertise(new ToolAdvertisement('read', 'desc', ['type' => 'object']));
        $this->llm->scriptToolCallTurn([new ToolCallRequest('0', 'read', ['filePath' => 'x'])]);
        $this->llm->scriptReply('done');

        self::assertSame('done', $this->runner->run('read x', 'ses_parent'));
        self::assertCount(1, $this->gateway->executions);
        self::assertSame('read', $this->gateway->executions[0]->name);
        self::assertSame('ses_parent', $this->gateway->sessionIds[0]);
    }

    public function testTaskAndTodowriteAreNotAdvertisedToTheSubAgent(): void
    {
        $this->gateway->advertise(
            new ToolAdvertisement('read', 'd', ['type' => 'object']),
            new ToolAdvertisement('task', 'd', ['type' => 'object']),
            new ToolAdvertisement('todowrite', 'd', ['type' => 'object']),
        );
        $this->llm->scriptReply('x');

        $this->runner->run('go', 'ses_parent');

        $advertised = array_map(static fn (ToolAdvertisement $t): string => $t->name, $this->llm->calls()[0]['tools']);
        self::assertSame(['read'], $advertised);
    }

    public function testRefusesToExecuteATaskCall(): void
    {
        $this->gateway->advertise(new ToolAdvertisement('read', 'd', ['type' => 'object']));
        // Even if the model somehow emits a task call, the runner must not run it.
        $this->llm->scriptToolCallTurn([new ToolCallRequest('0', 'task', ['prompt' => 'recurse'])]);
        $this->llm->scriptReply('stopped');

        self::assertSame('stopped', $this->runner->run('go', 'ses_parent'));
        self::assertSame([], $this->gateway->executions, 'A task call must never reach the gateway.');
    }

    public function testReturnsTurnLimitMessageWhenNeverTextual(): void
    {
        $this->gateway->advertise(new ToolAdvertisement('read', 'd', ['type' => 'object']));
        for ($i = 0; $i < AssistantSubAgentRunner::MAX_TURNS; ++$i) {
            $this->llm->scriptToolCallTurn([new ToolCallRequest((string) $i, 'read', ['filePath' => 'x'])]);
        }

        self::assertStringContainsString('turn limit', $this->runner->run('loop', 'ses_parent'));
    }
}
