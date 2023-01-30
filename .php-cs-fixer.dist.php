<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/includes')
    ->in(__DIR__ . '/tests')
;

$config = new PhpCsFixer\Config();
return $config->setRules([
    '@PSR12' => true,
    'braces' => false,
    'array_syntax' => ['syntax' => 'short'],
])
    ->setFinder($finder)
;
