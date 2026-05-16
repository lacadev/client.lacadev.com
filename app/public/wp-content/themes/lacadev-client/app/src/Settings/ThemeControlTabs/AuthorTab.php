<?php

namespace App\Settings\ThemeControlTabs;

class AuthorTab
{
    public function render(): void
    {
        $autoAppend = get_option('laca_author_bio_auto_append', '0');
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th>Auto-append bio box</th>
                <td>
                    <label>
                        <input type="checkbox" name="laca_author_bio_auto_append" value="1"
                               <?php checked('1', $autoAppend); ?>>
                        Tự động thêm Author Bio Box vào cuối mỗi bài viết đơn
                    </label>
                    <p class="description">
                        Nếu tắt, sử dụng shortcode <code>[laca_author_bio]</code> trong template.
                    </p>
                </td>
            </tr>
        </table>
        <h3>Shortcodes</h3>
        <table class="widefat striped" style="max-width:600px">
            <thead><tr><th>Shortcode</th><th>Mô tả</th></tr></thead>
            <tbody>
                <tr><td><code>[laca_author_bio]</code></td><td>Hiển thị bio box của tác giả bài viết hiện tại</td></tr>
                <tr><td><code>[laca_author_bio user_id="5"]</code></td><td>Hiển thị bio box của user cụ thể</td></tr>
                <tr><td><code>[laca_recommendations]</code></td><td>Hiển thị 3 bài viết liên quan có điểm cao nhất</td></tr>
                <tr><td><code>[laca_recommendations count="6"]</code></td><td>Hiển thị N bài liên quan</td></tr>
                <tr><td><code>[laca_lead_form id="123"]</code></td><td>Multi-step lead form</td></tr>
            </tbody>
        </table>
        <?php
    }

    public function save(array $post): void
    {
        update_option('laca_author_bio_auto_append', isset($post['laca_author_bio_auto_append']) ? '1' : '0');
    }
}
