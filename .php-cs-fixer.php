<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['vendor', 'storage', 'node_modules'])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'trailing_comma_in_multiline' => true,
        'line_ending' => false,
    ])
    ->setLineEnding("\r\n")
    ->setFinder($finder);
