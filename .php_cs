<?php
/**
 * https://dl2.tech - DL2 IT Services
 * Owlsome solutions. Owltstanding results.
 */

$paths = [
    'src',
    'tests',
];

$rules = [
    // '@PSR1'                         => true,
    '@PSR2'                         => true,
    '@Symfony'                      => true,
    '@Symfony:risky'                => true,
    'array_syntax'                  => ['syntax' => 'short'],
    'binary_operator_spaces'        => [
        'align_double_arrow'        => true,
        'align_equals'              => true,
    ],
    'braces'                        => ['allow_single_line_closure' => true],
    'cast_spaces'                   => true,
    'combine_consecutive_unsets'    => true,
    'concat_space'                  => ['spacing' => 'one'],
    'encoding'                      => true,
    'header_comment'                => [
        'commentType'   => 'PHPDoc',
        'header'        => implode("\n", [
            'https://dl2.tech - DL2 IT Services',
            'Owlsome solutions. Owltstanding results.',
        ]),
        'location'      => 'after_open',
        'separate'      => 'bottom',
    ],
    'heredoc_to_nowdoc'             => false,
    'is_null'                       => true,
    'linebreak_after_opening_tag'   => true,
    'ordered_class_elements'        => [
        'use_trait',
        'constant_public',
        'constant_protected',
        'constant_private',
        'property_public',
        'property_protected',
        'property_private',
        'construct',
        'destruct',
        'magic',
        'phpunit',
        'method_public',
        'method_protected',
        'method_private'
    ],
    'ordered_imports'               => true,
    'phpdoc_align'                  => false,
    'phpdoc_order'                  => true,
];

return PhpCsFixer\Config
    ::create()
    ->setRules($rules)
    ->setRiskyAllowed(true)
    ->setFinder(PhpCsFixer\Finder::create()->in($paths));
