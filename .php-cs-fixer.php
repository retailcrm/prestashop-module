<?php

require_once __DIR__ . '/tests/init.php';
require_once __DIR__ . '/../PrestaShop/vendor/friendsofphp/php-cs-fixer/src/Finder.php';
require_once __DIR__ . '/../PrestaShop/vendor/friendsofphp/php-cs-fixer/src/Config.php';




$finder = PhpCsFixer\Finder::create()->in([
    __DIR__.'/retailcrm'
]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        'array_indentation' => true,
        'cast_spaces' => [
            'space' => 'single',
        ],
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false
        ],

        'date_time_immutable' => true,
        'combine_consecutive_issets' => true,
        'concat_space' => [
            'spacing' => 'one',
        ],
        'error_suppression' => [
            'mute_deprecation_error' => false,
            'noise_remaining_usages' => false,
            'noise_remaining_usages_exclude' => [],
        ],
        'function_to_constant' => false,
        'method_chaining_indentation' => true,
        'no_alias_functions' => false,
        'no_superfluous_phpdoc_tags' => false,
        'non_printable_character' => [
            'use_escape_sequences_in_strings' => true,
        ],
        'phpdoc_align' => [
            'align' => 'left',
        ],
        'phpdoc_summary' => false,
        'protected_to_private' => false,
        'self_accessor' => false,
        'single_line_throw' => false,
        'no_alias_language_construct_call' => false,
        'visibility_required' => false,
        'ordered_imports' => true,
        'global_namespace_import' => [
            'import_classes' => false,
            'import_constants' => false,
            'import_functions' => false,
        ],
        'native_constant_invocation' => false,
        'native_function_invocation' => false,
        'modernize_types_casting' => true,
        'is_null' => true,
        'operator_linebreak' => [
            'only_booleans' => true,
            'position' => 'beginning',
        ],
        'ternary_to_null_coalescing' => false,
        'array_syntax' => [
            'syntax' => 'short',
        ],
        'phpdoc_annotation_without_dot' => false,
        'logical_operators' => true,
        'php_unit_test_case_static_method_calls' => ['call_type' => 'this'],
        'multiline_whitespace_before_semicolons' => ['strategy' => 'new_line_for_chained_calls'],

    ])
    ->setFinder($finder);