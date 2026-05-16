<?php

namespace App\Settings\ThemeControlTabs;

class SearchTab
{
    public function render(): void
    {
        $indexUrl = rest_url('lacadev/v1/search-index');
        ?>
        <h3>Smart Search Index</h3>
        <p>Search index được xây dựng từ WP REST API và cache tại client bằng Fuse.js.</p>
        <table class="form-table" role="presentation">
            <tr>
                <th>Index endpoint</th>
                <td>
                    <code><?php echo esc_html($indexUrl); ?></code>
                    <br><br>
                    <a href="<?php echo esc_url($indexUrl); ?>" target="_blank" class="button button-secondary">
                        Xem index JSON ↗
                    </a>
                    <button type="button" class="button" id="laca-bust-search-index"
                            data-nonce="<?php echo esc_attr(wp_create_nonce('laca_bust_search_index')); ?>">
                        ↻ Xóa cache index
                    </button>
                </td>
            </tr>
        </table>
        <script>
        document.getElementById('laca-bust-search-index')?.addEventListener('click', function(){
            this.disabled = true;
            this.textContent = 'Đang xóa…';
            fetch(ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=laca_bust_search_index&nonce=' + this.dataset.nonce
            }).then(r => r.json()).then(res => {
                this.disabled = false;
                this.textContent = res.success ? '✓ Đã xóa cache' : '✗ Lỗi';
            });
        });
        </script>
        <?php
    }
}
