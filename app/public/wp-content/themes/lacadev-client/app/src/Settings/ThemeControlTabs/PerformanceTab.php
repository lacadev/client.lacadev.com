<?php

namespace App\Settings\ThemeControlTabs;

class PerformanceTab
{
    public function render(): void
    {
        $cruxApiKey = get_option('laca_crux_api_key', '');
        $cruxUrl    = get_option('laca_crux_url', home_url('/'));
        ?>
        <h3>Core Web Vitals (CrUX)</h3>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="laca_crux_api_key">CrUX API Key</label></th>
                <td>
                    <input type="text" id="laca_crux_api_key" name="laca_crux_api_key"
                           value="<?php echo esc_attr($cruxApiKey); ?>" class="regular-text"
                           placeholder="AIza…">
                    <p class="description">
                        Lấy tại <strong>Google Cloud Console</strong> → APIs & Services → Credentials.
                        Không bắt buộc nhưng tăng quota từ 150 → 1500 req/ngày.
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="laca_crux_url">URL cần đo</label></th>
                <td>
                    <input type="url" id="laca_crux_url" name="laca_crux_url"
                           value="<?php echo esc_attr($cruxUrl); ?>" class="regular-text">
                    <p class="description">URL đại diện (thường là trang chủ). CrUX dùng field data thực tế.</p>
                </td>
            </tr>
        </table>

        <h3>Image Optimization</h3>
        <?php
        $avifSupport = function_exists('lacadev_imagick_supports_avif') && lacadev_imagick_supports_avif();
        $webpSupport = extension_loaded('imagick') || function_exists('imagewebp');
        ?>
        <table class="widefat striped" style="max-width:500px;margin-bottom:20px">
            <thead><tr><th>Format</th><th>Status</th></tr></thead>
            <tbody>
                <tr>
                    <td>WebP</td>
                    <td><?php echo $webpSupport ? '<span style="color:green">✓ Hỗ trợ</span>' : '<span style="color:red">✗ Không hỗ trợ</span>'; ?></td>
                </tr>
                <tr>
                    <td>AVIF</td>
                    <td><?php echo $avifSupport ? '<span style="color:green">✓ Hỗ trợ</span>' : '<span style="color:#999">— Cần Imagick + libavif</span>'; ?></td>
                </tr>
                <tr>
                    <td>LQIP</td>
                    <td><?php echo extension_loaded('imagick') ? '<span style="color:green">✓ Hỗ trợ</span>' : '<span style="color:#999">— Cần Imagick</span>'; ?></td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    public function save(array $post): void
    {
        update_option('laca_crux_api_key', sanitize_text_field($post['laca_crux_api_key'] ?? ''));
        update_option('laca_crux_url', esc_url_raw($post['laca_crux_url'] ?? ''));
        delete_transient('laca_cwv_data');
    }
}
