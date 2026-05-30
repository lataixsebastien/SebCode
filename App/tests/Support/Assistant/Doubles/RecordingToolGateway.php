<?php

declare(strict_types=1);

namespace App\Tests\Support\Assistant\Doubles;

use App\Assistant\Domain\Model\ValueObject\ToolAdvertisement;
use App\Assistant\Domain\Model\ValueObject\ToolCallRequest;
use App\Assistant\Domain\Model\ValueObject\ToolResultDto;
use App\Assistant\Domain\Port\ToolGateway;

/**
 * Scriptable ToolGateway double for SendMessageHandler tests.
 *
 *  - `availableTools()` returns whatever was registered via `advertise(...)`.
 *  - `execute()` consumes queued responses in order — pre-script with
 *     `scriptResult(toolCallId, ToolResultDto)` or `scriptException($e)`.
 *     If nothing is queued for a call, returns a generic success result.
 *  - Every call is captured in `$executions` for assertions.
 */
final class RecordingToolGateway implements ToolGateway
{
    /** @var list<ToolAdvertisement> */
    private array $advertised = [];

    /** @var list<ToolCallRequest> */
    public array $executions = [];

    /** @var list<ToolResultDto|\Throwable> */
    private array $scripted = [];

    public function advertise(ToolAdvertisement ...$tools): void
    {
        $this->advertised = array_values($tools);
    }

    public function scriptResult(ToolResultDto $result): void
    {
        $this->scripted[] = $result;
    }

    public function scriptException(\Throwable $exception): void
    {
        $this->scripted[] = $exception;
    }

    public function availableTools(): array
    {
        return $this->advertised;
    }

    public function execute(ToolCallRequest $request): ToolResultDto
    {
        $this->executions[] = $request;

        if ([] === $this->scripted) {
            return ToolResultDto::success($request->id, \sprintf('default output for %s', $request->name));
        }

        $next = array_shift($this->scripted);
        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }
}
