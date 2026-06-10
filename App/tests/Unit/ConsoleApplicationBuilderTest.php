<?php

declare(strict_types=1);

namespace SebCode\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SebCode\ConsoleApplicationBuilder;
use Symfony\Component\Console\Tester\CommandTester;

final class ConsoleApplicationBuilderTest extends TestCase
{
    public function testItCreatesVersionedConsoleApplication(): void
    {
        $application = ConsoleApplicationBuilder::build();

        self::assertSame('SebCode', $application->getName());
        self::assertSame('0.1.0-dev', $application->getVersion());
    }

    public function testAboutCommandDocumentsConsoleFirstRuntime(): void
    {
        $application = ConsoleApplicationBuilder::build();
        $command = $application->find('about');
        $tester = new CommandTester($command);

        $tester->execute([]);

        self::assertStringContainsString('console-first', $tester->getDisplay());
        self::assertStringContainsString('no HTTP application surface', $tester->getDisplay());
    }
}
