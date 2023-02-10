<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude('vendor')
    ->in(['src', 'spec'])
;

$config = new PhpCsFixer\Config();
return $config->setRules([
        '@PSR12' => true,
        'yoda_style' => true,
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder($finder)
;