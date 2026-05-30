<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Called by the framework via MicroKernelTrait to restrict allowed APP_ENV values.
     *
     * @return list<string>
     */
    private function getAllowedEnvs(): array // @phpstan-ignore method.unused
    {
        return ['prod', 'dev', 'test'];
    }
}
