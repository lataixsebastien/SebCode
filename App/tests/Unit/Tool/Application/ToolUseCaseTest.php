<?php

declare(strict_types=1);

namespace SebCode\Tests\Unit\Tool\Application;

use PHPUnit\Framework\TestCase;
use SebCode\Tool\Application\Dto\Input\ExecuteToolInput;
use SebCode\Tool\Application\Handler\ExecuteToolHandler;
use SebCode\Tool\Application\Handler\ListToolsHandler;
use SebCode\Tool\Application\UseCase\ExecuteToolUseCase;
use SebCode\Tool\Application\UseCase\ListToolsUseCase;
use SebCode\Tool\Domain\Exception\ToolNotFound;
use SebCode\Tool\Domain\Model\ToolDescriptor;
use SebCode\Tool\Domain\Model\ToolExecutionContext;
use SebCode\Tool\Domain\Model\ToolResult;
use SebCode\Tool\Domain\Port\Tool;
use SebCode\Tool\Infrastructure\Persistence\SqliteToolRepository;
use SebCode\Tool\Infrastructure\Repository\InMemoryToolRepository;

final class ToolUseCaseTest extends TestCase
{
    public function testItListsRegisteredToolDescriptorsInDeterministicOrder(): void
    {
        $repository = new InMemoryToolRepository();
        $repository->save(new EchoTool('beta'));
        $repository->save(new EchoTool('alpha'));

        $descriptors = $repository->descriptors();

        self::assertSame(['alpha', 'beta'], array_map(
            static fn (ToolDescriptor $descriptor): string => $descriptor->name,
            $descriptors,
        ));
    }

    public function testItRejectsDuplicateTools(): void
    {
        $repository = new InMemoryToolRepository();
        $repository->save(new EchoTool('echo'));

        $this->expectException(\InvalidArgumentException::class);

        $repository->save(new EchoTool('echo'));
    }

    public function testItThrowsWhenToolIsMissing(): void
    {
        $repository = new InMemoryToolRepository();

        $this->expectException(ToolNotFound::class);

        $repository->get('missing');
    }

    public function testItExposesRichInputSchema(): void
    {
        $repository = new InMemoryToolRepository();
        $repository->save(new EchoTool('echo'));

        $descriptor = $repository->get('echo')->descriptor();

        self::assertSame('test', $descriptor->category);
        self::assertTrue($descriptor->safe);
        self::assertSame(1, $descriptor->cost);
        self::assertSame(['READ_ONLY', 'PATCH'], $descriptor->allowedModes);
        self::assertSame(5, $descriptor->timeoutSeconds);
        self::assertFalse($descriptor->requiresReview);
        self::assertSame('object', $descriptor->inputSchema['type']);

        $properties = $descriptor->inputSchema['properties'] ?? null;
        self::assertIsArray($properties);
        self::assertArrayHasKey('message', $properties);
    }

    public function testExecutorRunsRegisteredTool(): void
    {
        $repository = new InMemoryToolRepository();
        $repository->save(new EchoTool('echo'));
        $handler = new ExecuteToolHandler($repository);

        $result = $handler(new ExecuteToolUseCase(new ExecuteToolInput('echo', '/workspace', [
            'message' => 'hello',
        ])));

        self::assertTrue($result->success);
        self::assertSame('/workspace:hello', $result->output);
        self::assertSame('hello', $result->metadata['received']);
    }

    public function testExecutorReturnsFailureWhenToolFails(): void
    {
        $repository = new InMemoryToolRepository();
        $repository->save(new FailingTool());
        $handler = new ExecuteToolHandler($repository);

        $result = $handler(new ExecuteToolUseCase(new ExecuteToolInput('fail', '/workspace', [])));

        self::assertFalse($result->success);
        self::assertSame('Tool failed.', $result->output);
        self::assertSame(\RuntimeException::class, $result->metadata['error']);
    }

    public function testSqliteRepositoryPersistsToolDescriptors(): void
    {
        $repository = new SqliteToolRepository(new \PDO('sqlite::memory:'));
        $repository->save(new EchoTool('beta'));
        $repository->save(new EchoTool('alpha'));

        $descriptors = $repository->descriptors();

        self::assertSame(['alpha', 'beta'], array_map(
            static fn (ToolDescriptor $descriptor): string => $descriptor->name,
            $descriptors,
        ));
        self::assertSame('Echo input.', $descriptors[0]->description);
        self::assertSame('test', $descriptors[0]->category);
        self::assertTrue($descriptors[0]->safe);
        self::assertSame(['READ_ONLY', 'PATCH'], $descriptors[0]->allowedModes);
        self::assertSame('object', $descriptors[0]->inputSchema['type']);
    }

    public function testListToolsHandlerFiltersByMode(): void
    {
        $repository = new InMemoryToolRepository();
        $repository->save(new EchoTool('readable', ['READ_ONLY']));
        $repository->save(new EchoTool('executable', ['EXECUTE']));

        $result = (new ListToolsHandler($repository))(new ListToolsUseCase('READ_ONLY'));

        self::assertCount(1, $result);
        self::assertSame('readable', $result[0]->name);
    }
}

final readonly class EchoTool implements Tool
{
    /**
     * @param list<string> $allowedModes
     */
    public function __construct(private string $name, private array $allowedModes = ['READ_ONLY', 'PATCH'])
    {
    }

    public function descriptor(): ToolDescriptor
    {
        return new ToolDescriptor(
            $this->name,
            'Echo input.',
            'test',
            true,
            1,
            $this->allowedModes,
            5,
            false,
            [
                'type' => 'object',
                'properties' => [
                    'message' => [
                        'type' => 'string',
                    ],
                ],
                'required' => ['message'],
            ],
        );
    }

    public function execute(ToolExecutionContext $context, array $input): ToolResult
    {
        $message = $input['message'] ?? '';
        if (!is_string($message)) {
            return ToolResult::failure('Message must be a string.');
        }

        return ToolResult::success($context->workspaceRoot.':'.$message, [
            'received' => $message,
        ]);
    }
}

final class FailingTool implements Tool
{
    public function descriptor(): ToolDescriptor
    {
        return new ToolDescriptor('fail', 'Always fail.');
    }

    public function execute(ToolExecutionContext $context, array $input): ToolResult
    {
        throw new \RuntimeException('Tool failed.');
    }
}
