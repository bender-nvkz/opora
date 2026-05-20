<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/config',
        __DIR__ . '/packages',
    ])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRiskyAllowed(true)
    ->setUsingCache(true)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setFinder($finder)
    ->setRules([
        // PSR-12 базовый набор
        '@PSR12'        => true,
        '@PSR12:risky'  => true,

        // PHP 8.4 specific (risky-вариант не существует — только базовый)
        '@PHP84Migration' => true,

        // Строгие типы — обязательно
        'declare_strict_types' => true,
        'strict_param'         => true,
        'strict_comparison'    => true,

        // Imports
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order'  => ['class', 'function', 'const'],
        ],
        'no_unused_imports'          => true,
        'global_namespace_import'    => [
            'import_classes'   => false,
            'import_constants' => false,
            'import_functions' => false,
        ],
        'single_import_per_statement'  => true,

        // Пробелы и форматирование
        'array_syntax'             => ['syntax' => 'short'],
        'list_syntax'              => ['syntax' => 'short'],
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters', 'match'],
        ],
        'no_trailing_whitespace'       => true,
        'no_whitespace_in_blank_line'  => true,
        'blank_line_after_namespace'   => true,
        'blank_line_after_opening_tag' => true,
        'blank_lines_before_namespace' => true,
        'single_blank_line_at_eof'     => true,
        'no_extra_blank_lines'         => [
            'tokens' => [
                'attribute',
                'break',
                'case',
                'continue',
                'curly_brace_block',
                'default',
                'extra',
                'parenthesis_brace_block',
                'return',
                'square_brace_block',
                'switch',
                'throw',
                'use',
            ],
        ],

        // Функции и методы
        'function_typehint_space'                              => true,
        'return_type_declaration'                              => ['space_before' => 'none'],
        'nullable_type_declaration_for_default_null_value'    => true,
        'nullable_type_declaration'                           => ['syntax' => 'union'],

        // Строки
        'single_quote'          => true,
        'no_mixed_echo_print'   => ['use' => 'echo'],
        'heredoc_to_nowdoc'     => true,

        // Классы
        'class_attributes_separation' => [
            'elements' => [
                'const'        => 'one',
                'method'       => 'one',
                'property'     => 'one',
                'trait_import' => 'none',
                'case'         => 'none',
            ],
        ],
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'case',
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
                'method_abstract',
                'method_public_static',
                'method_public',
                'method_protected_static',
                'method_protected',
                'method_private_static',
                'method_private',
            ],
            'sort_algorithm' => 'none',
        ],
        'no_null_property_initialization' => true,
        'self_static_accessor'            => true,
        'visibility_required'             => [
            'elements' => ['property', 'method', 'const'],
        ],

        // Документация
        'phpdoc_align'                  => ['align' => 'vertical'],
        'phpdoc_no_package'             => true,
        'phpdoc_order'                  => true,
        'phpdoc_separation'             => true,
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_trim'                   => true,
        'no_empty_phpdoc'              => true,
        'no_superfluous_phpdoc_tags'   => [
            'allow_mixed'       => true,
            'remove_inheritdoc' => false,
        ],

        // Условия и управление потоком
        'no_superfluous_elseif' => true,
        'no_useless_else'       => true,
        'no_useless_return'     => true,
        'simplified_if_return'  => true,
        'yoda_style'            => ['equal' => false, 'identical' => false, 'less_and_greater' => false],

        // Системные функции
        'native_function_invocation' => [
            'include' => ['@internal'],
            'scope'   => 'namespaced',
            'strict'  => false,
        ],

        // Прочее
        'concat_space'                           => ['spacing' => 'one'],
        'not_operator_with_successor_space'      => false,
        'binary_operator_spaces'                 => ['default' => 'single_space'],
        'cast_spaces'                            => ['space' => 'single'],
        'multiline_whitespace_before_semicolons' => ['strategy' => 'no_multi_line'],
        'semicolon_after_instruction'            => true,
    ]);
