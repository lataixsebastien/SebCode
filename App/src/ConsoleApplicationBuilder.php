<?php

declare(strict_types=1);

namespace SebCode;

use SebCode\UI\Console\AboutCommand;
use Symfony\Component\Console\Application;

final class ConsoleApplicationBuilder
{
    public const NAME = 'SebCode';

    public const VERSION = '0.1.0-dev';

    public static function build(): Application
    {
        $application = new Application(self::NAME, self::VERSION);
        $application->addCommand(new AboutCommand());

        return $application;
    }
}
