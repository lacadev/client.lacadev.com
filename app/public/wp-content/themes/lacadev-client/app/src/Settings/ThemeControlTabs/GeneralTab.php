<?php

namespace App\Settings\ThemeControlTabs;

class GeneralTab
{
    public function render(): void
    {
        $readingMode = get_option('laca_reading_mode_enabled', '1');
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th>Reading Mode</th>
                <td>
                    <label>
                        <input type="checkbox" name="laca_reading_mode_enabled" value="1"
                               <?php checked('1', $readingMode); ?>>
                        Bật nút Reading Mode trên bài viết
                    </label>
                    <p class="description">Khi tắt, theme sẽ không hiển thị nút chuyển sang chế độ đọc tập trung ở frontend.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save(array $post): void
    {
        update_option('laca_reading_mode_enabled', isset($post['laca_reading_mode_enabled']) ? '1' : '0');
    }
}
