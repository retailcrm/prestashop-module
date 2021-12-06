<?php

ini_set('memory_limit', '256M');

$finder = PhpCsFixer\Finder::create()->in([
    __DIR__ . '/retailcrm',
    __DIR__ . '/tests',
]);

$licenseHeader = 'MIT License

Copyright (c) 2021 DIGITAL RETAIL TECHNOLOGIES SL

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

DISCLAIMER

Do not edit or add to this file if you wish to upgrade PrestaShop to newer
versions in the future. If you wish to customize PrestaShop for your
needs please refer to http://www.prestashop.com for more information.

 @author    DIGITAL RETAIL TECHNOLOGIES SL <mail@simlachat.com>
 @copyright 2021 DIGITAL RETAIL TECHNOLOGIES SL
 @license   https://opensource.org/licenses/MIT  The MIT License

Don\'t forget to prefix your containers with your own identifier
to avoid any conflicts with others containers.';

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'psr_autoloading' => false,
        'array_indentation' => true,
        'cast_spaces' => [
            'space' => 'single',
        ],
        'yoda_style' => [
            'equal' => true,
            'identical' => true,
            'less_and_greater' => true,
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
        'dir_constant' => false,
        'header_comment' => [
            'header' => $licenseHeader,
            'comment_type' => 'PHPDoc',
            'location' => 'after_open',
            'separate' => 'bottom',
        ],
    ])
    ->setFinder($finder)
;
