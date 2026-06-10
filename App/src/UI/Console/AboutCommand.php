<?php

declare(strict_types=1);

namespace SebCode\UI\Console;

use SebCode\ConsoleApplicationBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'about', description: 'Display SebCode runtime information.')]
final class AboutCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf('%s %s', ConsoleApplicationBuilder::NAME, ConsoleApplicationBuilder::VERSION));
        $output->writeln('Runtime: console-first, local-only, no HTTP application surface');

        return Command::SUCCESS;
    }
}
