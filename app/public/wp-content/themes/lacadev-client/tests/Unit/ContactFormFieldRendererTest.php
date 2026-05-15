<?php

declare(strict_types=1);

use App\Features\ContactForm\ContactFormFieldRenderer;

if (!function_exists('esc_attr')) {
    function esc_attr(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

test('ContactFormFieldRenderer renders required email inputs with condition data', function (): void {
    $html = ContactFormFieldRenderer::renderSingle([
        'type' => 'email',
        'name' => 'email',
        'label' => 'Email',
        'placeholder' => 'you@example.com',
        'required' => true,
        'condition' => [
            'field' => 'service',
            'operator' => 'equals',
            'value' => 'web',
        ],
    ]);

    assert_true(str_contains($html, 'class="laca-cf-form-row laca-cf-type-email'));
    assert_true(str_contains($html, 'type="email"'));
    assert_true(str_contains($html, 'name="email"'));
    assert_true(str_contains($html, 'required data-required="true"'));
    assert_true(str_contains($html, 'data-condition-field="service"'));
});

test('ContactFormFieldRenderer skips step markers', function (): void {
    assert_same('', ContactFormFieldRenderer::renderSingle(['type' => 'step_break']));
});
