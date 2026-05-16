<?php

namespace App\Settings\Security\Admin;

class FimResultRenderer
{
    /**
     * @param array<string, mixed> $result
     */
    public function render(array $result): string
    {
        if (($result['total'] ?? 0) === 0) {
            return '<div class="notice notice-success inline"><p>✓ Không có thay đổi so với baseline <em>' . esc_html((string) ($result['base_time'] ?? '')) . '</em>.</p></div>';
        }

        ob_start();
        echo '<p>Phát hiện <strong>' . (int) $result['total'] . ' thay đổi</strong> so với baseline <em>' . esc_html((string) ($result['base_time'] ?? '')) . '</em>:</p>';

        foreach (['modified' => '🟡 Đã sửa', 'added' => '🟢 Mới thêm', 'deleted' => '🔴 Đã xóa'] as $key => $label) {
            if (empty($result[$key]) || !is_array($result[$key])) {
                continue;
            }

            echo '<h4 style="margin:12px 0 6px;">' . esc_html($label) . ' (' . count($result[$key]) . ')</h4>';
            echo '<table class="wp-list-table widefat striped"><thead><tr><th>File</th><th>Thời gian</th></tr></thead><tbody>';
            foreach ($result[$key] as $file) {
                echo '<tr><td><code>' . esc_html((string) ($file['path'] ?? '')) . '</code></td><td>' . esc_html((string) ($file['time'] ?? '')) . '</td></tr>';
            }
            echo '</tbody></table>';
        }

        return (string) ob_get_clean();
    }
}
