<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__.'/src')
    ->in(__DIR__.'/tests')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        // We stay on @PHP83Migration even though the runtime is PHP 8.4: the
        // @PHP84Migration set rewrites `(new Foo())->bar()` into the new
        // `new Foo()->bar()` form, which deptrac's bundled PHP parser
        // configuration does not accept yet. Revert when deptrac catches up.
        '@PHP83Migration' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'declare_strict_types' => true,
        'native_function_invocation' => ['include' => ['@compiler_optimized']],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true, 'remove_inheritdoc' => true],
        'phpdoc_align' => ['align' => 'left'],
        // The project is heavily attribute-based; phpdoc-only types are kept
        // explicitly for psalm/phpstan templating where attributes can't help.
        'phpdoc_to_comment' => false,
    ])
    ->setFinder($finder);
