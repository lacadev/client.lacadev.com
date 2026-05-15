<?php

declare(strict_types=1);

use App\Features\ContactForm\ContactFormSchema;

if (!function_exists('sanitize_email')) {
    function sanitize_email(string $value): string
    {
        return filter_var($value, FILTER_SANITIZE_EMAIL) ?: '';
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $value): string
    {
        return filter_var($value, FILTER_SANITIZE_URL) ?: '';
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        return trim(strip_tags($value));
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(string $value): string
    {
        return trim(strip_tags($value));
    }
}

if (!function_exists('is_email')) {
    function is_email(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $value): string
    {
        return strip_tags($value);
    }
}

function contact_form_schema_test_row(array $fields, int $span = 12): array
{
    return [
        'cols' => [[
            'span' => $span,
            'fields' => $fields,
        ]],
    ];
}

test('ContactFormSchema extracts row-based fields without step markers', function (): void {
    $form = [
        'fields' => json_encode([
            contact_form_schema_test_row([
                ['type' => 'text', 'name' => 'name'],
                ['type' => 'email', 'name' => 'email'],
            ]),
            contact_form_schema_test_row([
                ['type' => 'step_break', 'label' => 'Details'],
                ['type' => 'textarea', 'name' => 'message'],
            ]),
        ]),
    ];

    $fields = ContactFormSchema::extractFlatFields($form);

    assert_same(['name', 'email', 'message'], array_column($fields, 'name'));
});

test('ContactFormSchema splits multi-step rows around step markers', function (): void {
    $rows = [
        contact_form_schema_test_row([
            ['type' => 'text', 'name' => 'name'],
        ]),
        contact_form_schema_test_row([
            ['type' => 'step_break', 'label' => 'Thông tin thêm'],
            ['type' => 'email', 'name' => 'email'],
        ]),
    ];

    assert_true(ContactFormSchema::shouldRenderMultiStep($rows, []));

    $steps = ContactFormSchema::splitRowsIntoSteps($rows);

    assert_same(2, count($steps));
    assert_same('Bước 1', $steps[0]['label']);
    assert_same('Thông tin thêm', $steps[1]['label']);
    assert_same('email', $steps[1]['rows'][0]['cols'][0]['fields'][0]['name']);
});

test('ContactFormSchema converts old flat fields into builder rows', function (): void {
    $rows = ContactFormSchema::toRowsFormat([
        'fields' => json_encode([
            ['id' => 'name', 'type' => 'text', 'name' => 'name', 'col_width' => 6],
            ['id' => 'note', 'type' => 'textarea', 'name' => 'note', 'col_width' => 5],
        ]),
    ], [3, 4, 6, 8, 12]);

    assert_same(2, count($rows));
    assert_same(6, $rows[0]['cols'][0]['span']);
    assert_same(12, $rows[1]['cols'][0]['span'], 'Unsupported spans should fall back to 12.');
    assert_true(!array_key_exists('col_width', $rows[0]['cols'][0]['fields'][0]));
});

test('ContactFormSchema evaluates conditional fields against scalar and array input', function (): void {
    $field = [
        'condition' => [
            'field' => 'service',
            'operator' => 'contains',
            'value' => 'web',
        ],
    ];

    assert_true(ContactFormSchema::isFieldConditionMatched($field, ['service' => ['web', 'seo']]));
    assert_true(!ContactFormSchema::isFieldConditionMatched($field, ['service' => 'hosting']));

    $emptyField = [
        'condition' => [
            'field' => 'note',
            'operator' => 'empty',
            'value' => '',
        ],
    ];

    assert_true(ContactFormSchema::isFieldConditionMatched($emptyField, ['note' => '']));
});

test('ContactFormSchema sanitizes options and validates common formats', function (): void {
    $clean = ContactFormSchema::sanitizeByType('checkbox', ['A', 'C'], ['options' => ['A', 'B']]);
    assert_same([0 => 'A'], $clean);

    assert_same('', ContactFormSchema::sanitizeByType('select', 'C', ['options' => ['A', 'B']]));
    assert_same('', ContactFormSchema::validateFormat('email', 'hello@example.com', 'Email'));
    assert_true(ContactFormSchema::validateFormat('phone', '123', 'Phone') !== '');
    assert_true(ContactFormSchema::validateFormat('number', 'abc', 'Amount') !== '');
});

test('ContactFormSchema builds scoped CSS variables and custom selectors', function (): void {
    $css = ContactFormSchema::buildScopedCss('laca-cf-10', [
        'primary_color' => '#2271b1',
        'btn_border_radius' => '12',
        'show_label' => false,
        'custom_css' => '__FORM__ .custom { color: <strong>red</strong>; }',
    ]);

    assert_true(str_contains($css, '#laca-cf-10{'));
    assert_true(str_contains($css, '--cf-primary:#2271b1'));
    assert_true(str_contains($css, '--cf-btn-radius:12px'));
    assert_true(str_contains($css, '--cf-label-display:none'));
    assert_true(str_contains($css, '#laca-cf-10 .custom'));
    assert_true(!str_contains($css, '<strong>'));
});
