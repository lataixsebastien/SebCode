<?php

declare(strict_types=1);

namespace SebCode\Tests\Security;

use PHPUnit\Framework\TestCase;
use SebCode\Permission\Application\Dto\Input\EvaluatePermissionInput;
use SebCode\Permission\Application\Dto\Output\PermissionDecisionOutput;
use SebCode\Permission\Application\Handler\EvaluatePermissionHandler;
use SebCode\Permission\Application\UseCase\EvaluatePermissionUseCase;
use SebCode\Permission\Domain\DecisionEngine;
use SebCode\Permission\Domain\Model\PermissionContext;
use SebCode\Permission\Domain\Model\PermissionRequest;
use SebCode\Permission\Domain\Model\ValueObject\PermissionReason;
use SebCode\Permission\Domain\PermissionPolicy;
use SebCode\Permission\Infrastructure\Repository\InMemoryApprovalStore;
use SebCode\Permission\Infrastructure\Security\SecurityNetworkAccessPolicyAdapter;
use SebCode\Permission\Infrastructure\Workspace\WorkspaceAccessPolicyAdapter;
use SebCode\Security\Domain\NetworkPolicy;
use SebCode\Workspace\Application\Handler\ResolveWorkspacePathHandler;
use SebCode\Workspace\Domain\IgnoreMatcher;
use SebCode\Workspace\Domain\PathNormalizer;
use SebCode\Workspace\Domain\WorkspaceGuard;
use SebCode\Workspace\Infrastructure\Filesystem\NativeFilesystemProbe;

final class PermissionPolicyTest extends TestCase
{
    private string $workspaceRoot = '';

    protected function setUp(): void
    {
        $this->workspaceRoot = dirname(__DIR__, 2).'/var/cache/permission-policy-tests/workspace';
        $this->removeDirectory(dirname($this->workspaceRoot));

        mkdir($this->workspaceRoot.'/src', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(dirname($this->workspaceRoot));
    }

    public function testReadSrcIsAllowed(): void
    {
        $decision = $this->decide(PermissionRequest::readFile('src/Foo.php'));

        self::assertTrue($decision->allowed);
        self::assertFalse($decision->requiresApproval);
        self::assertSame(PermissionReason::Allowed, $decision->reason);
    }

    public function testReadEnvIsDenied(): void
    {
        $decision = $this->decide(PermissionRequest::readFile('.env'));

        self::assertTrue($decision->isDenied());
        self::assertSame(PermissionReason::WorkspaceViolation, $decision->reason);
    }

    public function testWriteSrcRequiresApproval(): void
    {
        $decision = $this->decide(PermissionRequest::writeFile('src/Foo.php'));

        self::assertFalse($decision->allowed);
        self::assertTrue($decision->requiresApproval);
        self::assertSame(PermissionReason::WriteRequiresApproval, $decision->reason);
    }

    public function testCurlIsDenied(): void
    {
        $decision = $this->decide(PermissionRequest::runCommand('curl https://example.com'));

        self::assertTrue($decision->isDenied());
        self::assertSame(PermissionReason::CommandDenied, $decision->reason);
    }

    public function testPhpunitRequiresApproval(): void
    {
        $decision = $this->decide(PermissionRequest::runCommand('vendor/bin/phpunit'));

        self::assertFalse($decision->allowed);
        self::assertTrue($decision->requiresApproval);
        self::assertSame(PermissionReason::CommandRequiresApproval, $decision->reason);
    }

    public function testLocalNetworkIsAllowed(): void
    {
        $decision = $this->decide(PermissionRequest::network('http://127.0.0.1:11434/api/generate'));

        self::assertTrue($decision->allowed);
        self::assertSame(PermissionReason::Allowed, $decision->reason);
    }

    public function testExternalNetworkIsDenied(): void
    {
        $decision = $this->decide(PermissionRequest::network('https://example.com'));

        self::assertTrue($decision->isDenied());
        self::assertSame(PermissionReason::NetworkDenied, $decision->reason);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);
        if (false === $entries) {
            return;
        }

        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $entryPath = $path.'/'.$entry;
            if (is_dir($entryPath) && !is_link($entryPath)) {
                $this->removeDirectory($entryPath);
                continue;
            }

            unlink($entryPath);
        }

        rmdir($path);
    }

    private function decide(PermissionRequest $request): PermissionDecisionOutput
    {
        return ($this->createHandler())(new EvaluatePermissionUseCase(new EvaluatePermissionInput(
            $request,
            new PermissionContext($this->workspaceRoot),
        )));
    }

    private function createHandler(): EvaluatePermissionHandler
    {
        $resolveWorkspacePathHandler = new ResolveWorkspacePathHandler(new WorkspaceGuard(
            new PathNormalizer(),
            new IgnoreMatcher(),
            new NativeFilesystemProbe(),
        ));

        return new EvaluatePermissionHandler(new PermissionPolicy(
            new WorkspaceAccessPolicyAdapter($resolveWorkspacePathHandler),
            new SecurityNetworkAccessPolicyAdapter(new NetworkPolicy()),
            new InMemoryApprovalStore(),
            new DecisionEngine(),
        ));
    }
}
