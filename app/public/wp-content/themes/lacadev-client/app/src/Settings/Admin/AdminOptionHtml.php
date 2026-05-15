<?php

namespace App\Settings\Admin;

/**
 * Small HTML snippets used by Carbon Fields admin option pages.
 */
final class AdminOptionHtml
{
    public static function blockSyncApiKey(string $key): string
    {
        return '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:12px 16px;margin:8px 0">'
            . '<p style="margin:0 0 6px;font-weight:600;color:#166534">🔑 API Key của site này:</p>'
            . '<code style="font-size:13px;word-break:break-all;background:#dcfce7;padding:6px 10px;border-radius:4px;display:block">' . esc_html($key) . '</code>'
            . '<p style="margin:8px 0 0;font-size:12px;color:#4b5563">Copy key này và dán vào tab <strong>🧩 Block Sync</strong> trong project trên <strong>lacadev.com</strong>.</p>'
            . '</div>';
    }

    public static function blockSyncEndpoint(string $endpoint): string
    {
        return '<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:12px 16px;margin:8px 0">'
            . '<p style="margin:0 0 6px;font-weight:600;color:#0369a1">🌐 Endpoint URL của site này:</p>'
            . '<code style="font-size:13px;word-break:break-all;background:#e0f2fe;padding:6px 10px;border-radius:4px;display:block">' . esc_html($endpoint) . '</code>'
            . '<p style="margin:8px 0 0;font-size:12px;color:#4b5563">Dán URL này vào trường <strong>Sync Endpoint URL</strong> trong project tương ứng trên lacadev.com.</p>'
            . '</div>';
    }

    public static function trackerInfo(): string
    {
        return '<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0">'
            . '<p style="margin:0 0 8px;font-weight:600;color:#0369a1">📡 LacaDev Tracker</p>'
            . '<p style="margin:0;font-size:13px;color:#374151">Gửi log tự động (cập nhật plugin/theme/core, xóa plugin, phát hiện file PHP lạ) về hệ thống quản lý dự án lacadev.com. '
            . 'Lấy <strong>Endpoint URL</strong> và <strong>Secret Key</strong> từ trang chi tiết project tương ứng trên lacadev.com.</p>'
            . '<p style="margin:10px 0 0;font-size:12px;color:#475569">Endpoint hỗ trợ khách gửi yêu cầu tại site này: <code>/wp-json/laca/v1/client/request</code>.</p>'
            . '</div>';
    }

    public static function trackerStatus(bool $configured): string
    {
        if ($configured) {
            return '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:10px 14px;margin:8px 0;color:#166534;font-weight:600">✅ Tracker đã được cấu hình</div>';
        }

        return '<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:6px;padding:10px 14px;margin:8px 0;color:#c2410c;font-weight:600">⚠️ Chưa cấu hình — nhập Endpoint và Secret Key bên dưới để kích hoạt</div>';
    }

    public static function trackerSaveNote(): string
    {
        return '<p style="font-size:12px;color:#6b7280;margin-top:4px">'
            . 'Sau khi lưu, tracker sẽ tự động gửi log khi có cập nhật plugin/theme/core hoặc phát hiện file PHP lạ trong <code>wp-content/uploads</code>.'
            . '</p>';
    }
}
