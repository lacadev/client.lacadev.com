<?php

declare(strict_types=1);

use App\Settings\Tracker\TrackerShortcodeRenderer;

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

if (!function_exists('esc_js')) {
    function esc_js(mixed $value): string
    {
        return addslashes((string) $value);
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $value): string
    {
        return filter_var($value, FILTER_SANITIZE_URL) ?: '';
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = ''): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('date_i18n')) {
    function date_i18n(string $format, int $timestamp): string
    {
        return date($format, $timestamp);
    }
}

test('TrackerShortcodeRenderer renders support center form markup and AJAX endpoint', function (): void {
    $html = TrackerShortcodeRenderer::supportCenter(
        'Support',
        'extra-class',
        'https://example.test/wp-json/laca/v1/client/request',
        'support-form',
        'https://example.test/page/'
    );

    assert_true(str_contains($html, 'class="laca-support-center extra-class"'));
    assert_true(str_contains($html, 'id="support-form"'));
    assert_true(str_contains($html, 'name="attachments[]"'));
    assert_true(str_contains($html, 'https://example.test/wp-json/laca/v1/client/request'));
    assert_true(str_contains($html, "new FormData(form)"));
});

test('TrackerShortcodeRenderer renders timeline items and empty states', function (): void {
    $html = TrackerShortcodeRenderer::maintenanceTimeline('History', '', [[
        'title' => 'Plugin update',
        'message' => 'Updated Akismet',
        'tone' => 'done',
        'status_label' => 'Hoàn tất',
        'time' => '2026-05-15 10:00:00',
    ]]);

    assert_true(str_contains($html, 'laca-maintenance-timeline__badge--done'));
    assert_true(str_contains($html, 'Plugin update'));
    assert_true(str_contains($html, 'Updated Akismet'));

    $empty = TrackerShortcodeRenderer::maintenanceTimeline('History', '', []);
    assert_true(str_contains($empty, 'Chưa có hoạt động bảo trì nào được ghi nhận.'));
});
