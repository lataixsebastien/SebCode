<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Domain\Service;

use App\Tool\Domain\Exception\PathNotAllowed;
use App\Tool\Domain\Service\WorkspacePath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WorkspacePath::class)]
final class WorkspacePathTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $root = realpath(sys_get_temp_dir()).\DIRECTORY_SEPARATOR.'sebcode_ws_'.bin2hex(random_bytes(4));
        mkdir($root, 0o777, true);
        $this->root = $root;
    }

    protected function tearDown(): void
    {
        @rmdir($this->root);
    }

    public function testResolvesRelativePathOntoRoot(): void
    {
        self::assertSame(
            $this->root.\DIRECTORY_SEPARATOR.'src'.\DIRECTORY_SEPARATOR.'Foo.php',
            WorkspacePath::resolveForWrite($this->root, 'src/Foo.php'),
        );
    }

    public function testAbsolutePathInsideWorkspaceIsNotDoubled(): void
    {
        // The regression: an absolute in-root path must resolve to itself, not
        // be re-joined onto the root ("/root/var/www/App/x" style doubling).
        $absolute = $this->root.'/resume.md';

        self::assertSame(
            $this->root.\DIRECTORY_SEPARATOR.'resume.md',
            WorkspacePath::resolveForWrite($this->root, $absolute),
        );
    }

    public function testAbsolutePathOutsideWorkspaceIsRejected(): void
    {
        $this->expectException(PathNotAllowed::class);
        WorkspacePath::resolveForWrite($this->root, '/etc/passwd');
    }

    public function testTraversalIsRejected(): void
    {
        $this->expectException(PathNotAllowed::class);
        WorkspacePath::resolveForWrite($this->root, '../escape.txt');
    }

    public function testRelativePatternStripsAbsoluteInRootPrefix(): void
    {
        self::assertSame(
            'resume.md',
            WorkspacePath::relativePattern($this->root, $this->root.'/resume.md'),
        );
    }

    public function testRelativePatternLeavesRelativePathClean(): void
    {
        self::assertSame(
            'src/Foo.php',
            WorkspacePath::relativePattern($this->root, './src/Foo.php'),
        );
    }
}
