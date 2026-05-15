<?php

declare(strict_types=1);

use App\Features\ContactForm\ContactFormEditPageRenderer;
use App\Features\ContactForm\ContactFormListPageRenderer;
use App\Features\ContactForm\ContactFormSubmissionsPageRenderer;

if (!function_exists('esc_url')) {
    function esc_url(string $value): string
    {
        return $value;
    }
}

if (!function_exists('esc_html')) {
    function esc_html(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_js')) {
    function esc_js(mixed $value): string
    {
        return addslashes((string) $value);
    }
}

if (!function_exists('esc_textarea')) {
    function esc_textarea(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field(string $action, string $name): void
    {
        echo '<input type="hidden" name="' . $name . '" value="' . $action . '">';
    }
}

if (!function_exists('checked')) {
    function checked(mixed $checked, mixed $current = true): void
    {
        if ($checked === $current) {
            echo 'checked';
        }
    }
}

if (!function_exists('disabled')) {
    function disabled(mixed $disabled, bool $current = true): void
    {
        if ((bool) $disabled === $current) {
            echo 'disabled';
        }
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $data, int $flags = 0, int $depth = 512): string|false
    {
        return json_encode($data, $flags, $depth);
    }
}

test('ContactFormListPageRenderer renders populated list items and empty states', function (): void {
    $emptyHtml = ContactFormListPageRenderer::render([
        'page_url' => '/admin?page=forms',
        'message' => null,
        'items' => [],
        'nonce_action' => 'nonce-action',
        'nonce_field' => '_nonce',
    ]);
    assert_true(str_contains($emptyHtml, 'Tạo form đầu tiên'));

    $filledHtml = ContactFormListPageRenderer::render([
        'page_url' => '/admin?page=forms',
        'message' => ['type' => 'success', 'text' => 'Saved'],
        'items' => [[
            'id' => 7,
            'name' => 'Contact',
            'field_count' => 3,
            'submission_count' => 12,
            'unread_count' => 2,
            'shortcode' => '[laca_contact_form id="7"]',
            'edit_url' => '/edit',
            'submissions_url' => '/subs',
            'delete_action' => '/delete',
        ]],
        'nonce_action' => 'nonce-action',
        'nonce_field' => '_nonce',
    ]);
    assert_true(str_contains($filledHtml, 'Contact'));
    assert_true(str_contains($filledHtml, '2 mới'));
    assert_true(str_contains($filledHtml, '[laca_contact_form id=&quot;7&quot;]'));
});

test('ContactFormEditPageRenderer renders edit titles and serialized builder state', function (): void {
    $html = ContactFormEditPageRenderer::render([
        'is_new' => false,
        'page_url' => '/admin?page=forms',
        'form_action' => '/admin-post.php',
        'form_id' => 7,
        'form_name' => 'Contact',
        'rows' => [['id' => 'row-1']],
        'style_json' => '{"primary_color":"#000"}',
        'message' => ['type' => 'success', 'text' => 'Saved'],
        'notify_email' => 'hello@example.com',
        'admin_email' => 'admin@example.com',
        'use_admin_notify_email' => false,
        'email_admin_subject' => 'Admin subject',
        'email_admin_body' => '<p>Admin body</p>',
        'email_customer_subject' => 'Customer subject',
        'email_customer_body' => '<p>Customer body</p>',
        'field_types' => ['text' => 'Text'],
        'nonce_action' => 'nonce-action',
        'nonce_field' => '_nonce',
    ]);

    assert_true(str_contains($html, '✏️ Sửa Form: Contact'));
    assert_true(str_contains($html, 'fields-json-input'));
    assert_true(str_contains($html, 'window.LacaContactFormVars'));
    assert_true(str_contains($html, 'Customer subject'));
});

test('ContactFormSubmissionsPageRenderer renders rows and pagination controls', function (): void {
    $html = ContactFormSubmissionsPageRenderer::render([
        'form_id' => 7,
        'form_name' => 'Contact',
        'page_url' => '/admin?page=forms',
        'total' => 2,
        'message' => ['type' => 'success', 'text' => 'Done'],
        'fields' => [['label' => 'Tên']],
        'items' => [[
            'id' => 9,
            'is_read' => false,
            'values' => ['An'],
            'ip_address' => '127.0.0.1',
            'created_at' => '15/05/2026 10:00',
            'mark_url' => '/mark',
            'delete_url' => '/delete',
        ]],
        'pages' => 2,
        'page' => 1,
        'export_url' => '/export',
    ]);

    assert_true(str_contains($html, '📥 Submissions'), 'Expected submissions heading.');
    assert_true(str_contains($html, 'laca-cf-row-unread'), 'Expected unread row class.');
    assert_true(str_contains($html, '127.0.0.1'), 'Expected IP address.');
    assert_true(str_contains($html, 'paged=2'), 'Expected pagination link for page 2.');
});
