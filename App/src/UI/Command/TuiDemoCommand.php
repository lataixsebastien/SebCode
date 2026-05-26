<?php

namespace App\UI\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\TextWidget;

#[AsCommand(name: 'app:tui:demo', description: 'Run a minimal Symfony TUI demo')]
final class TuiDemoCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $styleSheet = new StyleSheet([
            ':root' => new Style(background: '#0b1220', color: '#e5e7eb'),
            '#title' => new Style(color: '#60a5fa', bold: true),
            '#hint' => new Style(color: '#94a3b8'),
        ]);

        $tui = new Tui($styleSheet);
        $title = new TextWidget('SebCode TUI is ready on Symfony 8.1 + PHP 8.4.')->setId('title');
        $hint = new TextWidget('Press Ctrl+C to exit.')->setId('hint');

        $tui->add($title);
        $tui->add($hint);
        $tui->run();

        return Command::SUCCESS;
    }
}
