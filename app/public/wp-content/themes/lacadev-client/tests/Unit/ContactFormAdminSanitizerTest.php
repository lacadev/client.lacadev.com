<?php

declare(strict_types=1);

use App\Features\ContactForm\ContactFormAdminSanitizer;

if (!function_exists('sanitize_key')) {
    function sanitize_key(mixed $value): string
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)) ?? '';
    }
}

if (!function_exists('sanitize_hex_color')) {
    function sanitize_hex_color(mixed $value): string
    {
        $value = (string) $value;
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : '';
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(mixed $value): string
    {
        return trim(strip_tags((string) $value));
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $value): string
    {
        return strip_tags($value);
    }
}

test('ContactFormAdminSanitizer sanitizes builder rows and conditions', function (): void {
    $rows = ContactFormAdminSanitizer::rows([
        [
            'id' => 'Row One',
            'cols' => [[
                'id' => 'Col One',
                'span' => 5,
                'fields' => [
                    [
                        'id' => 'Email Field',
                        'type' => 'email',
                        'name' => 'User Email',
                        'label' => '<b>Email</b>',
                        'required' => true,
                        'options' => [' A ', '<b>B</b>'],
                        'condition' => [
                            'field' => 'Service Type',
                            'operator' => 'invalid',
                            'value' => '<i>web</i>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'name' => '',
                        'label' => 'Skip me',
                    ],
                ],
            ]],
        ],
    ], ['text' => 'Text', 'email' => 'Email', 'step_break' => 'Step'], [3, 4, 6, 8, 12]);

    assert_same('rowone', $rows[0]['id']);
    assert_same(12, $rows[0]['cols'][0]['span']);
    assert_same(1, count($rows[0]['cols'][0]['fields']));
    assert_same('emailfield', $rows[0]['cols'][0]['fields'][0]['id']);
    assert_same('useremail', $rows[0]['cols'][0]['fields'][0]['name']);
    assert_same('Email', $rows[0]['cols'][0]['fields'][0]['label']);
    assert_same('equals', $rows[0]['cols'][0]['fields'][0]['condition']['operator']);
});

test('ContactFormAdminSanitizer applies defaults for step markers', function (): void {
    $rows = ContactFormAdminSanitizer::rows([
        [
            'cols' => [[
                'span' => 6,
                'fields' => [[
                    'type' => 'step_break',
                    'label' => '',
                ]],
            ]],
        ],
    ], ['text' => 'Text', 'step_break' => 'Step'], [3, 4, 6, 8, 12]);

    $field = $rows[0]['cols'][0]['fields'][0];

    assert_same('step_break', $field['type']);
    assert_same('Bước tiếp theo', $field['label']);
    assert_same('', $field['name']);
    assert_same([], $field['condition']);
});

test('ContactFormAdminSanitizer sanitizes style settings', function (): void {
    $style = ContactFormAdminSanitizer::style([
        'primary_color' => '#ABCDEF',
        'secondary_color' => 'invalid',
        'btn_border_radius' => '99',
        'input_border_radius' => '-10',
        'btn_text' => '<b>Send</b>',
        'form_mode' => 'multi_step',
        'step_next_text' => '<i>Next</i>',
        'input_spacing' => ' 10px 14px ',
        'hide_labels' => '1',
        'custom_css' => '.x{color:<b>red</b>;}',
    ]);

    assert_same('#abcdef', $style['primary_color']);
    assert_true(!array_key_exists('secondary_color', $style));
    assert_same(50, $style['btn_border_radius']);
    assert_same(0, $style['input_border_radius']);
    assert_same('Send', $style['btn_text']);
    assert_same('multi_step', $style['form_mode']);
    assert_same('Next', $style['step_next_text']);
    assert_same('10px 14px', $style['input_spacing']);
    assert_same(true, $style['hide_labels']);
    assert_same('.x{color:red;}', $style['custom_css']);
});
