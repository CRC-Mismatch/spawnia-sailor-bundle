<?php

$finder = (new PhpCsFixer\Finder())
    ->name('*.php')
    ->in(__DIR__)
    ->exclude(['vendor', 'var']);

$config = (new PhpCsFixer\Config())
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
            'sort_algorithm' => 'alpha',
        ],
        'linebreak_after_opening_tag' => true,
        'modernize_types_casting' => true,
        'no_superfluous_elseif' => true,
        'no_useless_else' => true,
        'phpdoc_order' => true,
        'psr_autoloading' => true,
        'simplified_null_return' => true,
        'php_unit_strict' => true,
        'no_useless_return' => true,
        'strict_param' => true,
        'strict_comparison' => true,
        'declare_strict_types' => true,
        'global_namespace_import' => [
            'import_constants' => true,
            'import_functions' => true,
            'import_classes' => true,
        ],
    ]);

return $config;
