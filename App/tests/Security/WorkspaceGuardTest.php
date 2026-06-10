<?php

declare(strict_types=1);

namespace SebCode\Tests\Security;

use PHPUnit\Framework\TestCase;
use SebCode\Workspace\Application\Dto\Input\ResolveWorkspacePathInput;
use SebCode\Workspace\Application\Handler\ResolveWorkspacePathHandler;
use SebCode\Workspace\Application\UseCase\ResolveWorkspaceUseCase;
use SebCode\Workspace\Domain\Exception\WorkspaceViolation;
use SebCode\Workspace\Domain\IgnoreMatcher;
use SebCode\Workspace\Domain\PathNormalizer;
use SebCode\Workspace\Domain\WorkspaceGuard;
use SebCode\Workspace\Infrastructure\Filesystem\NativeFilesystemProbe;

final class WorkspaceGuardTest extends TestCase
{
    private string $workspaceRoot = '';

    private string $outsideRoot = '';

    protected function setUp(): void
    {
        $basePath = dirname(__DIR__, 2).'/var/cache/security-tests';
        $this->removeDirectory($basePath);

        $this->workspaceRoot = $basePath.'/workspace';
        $this->outsideRoot = $basePath.'/outside';

        mkdir($this->workspaceRoot.'/src', 0777, true);
        mkdir($this->outsideRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(dirname(__DIR__, 2).'/var/cache/security-tests');
    }

    public function testItAcceptsWorkspaceRelativePath(): void
    {
        $handler = $this->createHandler();

        $allowedPath = $handler(new ResolveWorkspaceUseCase(new ResolveWorkspacePathInput($this->workspaceRoot, 'src/Foo.php')));

        self::assertSame($this->workspaceRoot.'/src/Foo.php', $allowedPath->absolutePath);
        self::assertSame('src/Foo.php', $allowedPath->relativePath);
    }

    public function testItRejectsParentTraversal(): void
    {
        $handler = $this->createHandler();

        $this->expectException(WorkspaceViolation::class);

        $handler(new ResolveWorkspaceUseCase(new ResolveWorkspacePathInput($this->workspaceRoot, '../secret.txt')));
    }

    public function testItRejectsHomePath(): void
    {
        $home = getenv('HOME');
        if (!is_string($home) || '' === $home) {
            self::markTestSkipped('No HOME environment variable is available.');
        }

        $handler = $this->createHandler();

        $this->expectException(WorkspaceViolation::class);

        $handler(new ResolveWorkspaceUseCase(new ResolveWorkspacePathInput($this->workspaceRoot, $home.'/secret.txt')));
    }

    public function testItRejectsSecretPaths(): void
    {
        $handler = $this->createHandler();

        $this->expectException(WorkspaceViolation::class);

        $handler(new ResolveWorkspaceUseCase(new ResolveWorkspacePathInput($this->workspaceRoot, '.env')));
    }

    public function testItRejectsSymlinkOutsideWorkspace(): void
    {
        file_put_contents($this->outsideRoot.'/secret.txt', 'secret');

        if (!symlink($this->outsideRoot.'/secret.txt', $this->workspaceRoot.'/linked-secret')) {
            self::markTestSkipped('Unable to create symlink on this platform.');
        }

        $handler = $this->createHandler();

        $this->expectException(WorkspaceViolation::class);

        $handler(new ResolveWorkspaceUseCase(new ResolveWorkspacePathInput($this->workspaceRoot, 'linked-secret')));
    }

    private function createHandler(): ResolveWorkspacePathHandler
    {
        return new ResolveWorkspacePathHandler(new WorkspaceGuard(
            new PathNormalizer(),
            new IgnoreMatcher(),
            new NativeFilesystemProbe(),
        ));
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
}
