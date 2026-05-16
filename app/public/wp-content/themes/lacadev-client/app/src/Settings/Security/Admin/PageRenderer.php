<?php

namespace App\Settings\Security\Admin;

class PageRenderer
{
    /**
     * @param array<string, string> $tabs
     */
    public function renderPage(string $activeTab, array $tabs): void
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($tabs[$activeTab]); ?></h1>
            <div class="tab-content" style="background:#fff;padding:24px;border:1px solid #dfe3ea;border-radius:10px;margin-top:16px;">
                <?php
                switch ($activeTab) {
                    case 'audit':
                        $this->renderAuditTab();
                        break;
                    case 'fim':
                        $this->renderFimTab();
                        break;
                    case 'malware':
                        $this->renderMalwareTab();
                        break;
                    case 'users':
                        $this->renderUsersTab();
                        break;
                    case 'login':
                        $this->renderLoginTab();
                        break;
                    case '2fa':
                        $this->render2faTab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    private function renderAuditTab(): void
    {
        ?>
        <h2>Kiểm tra bảo mật tổng quan</h2>
        <p>Đánh giá điểm bảo mật site theo nhiều hạng mục: WordPress Core, đăng nhập, server, HTTP headers...</p>
        <button id="btn-run-audit" class="button button-primary">▶ Chạy kiểm tra</button>
        <div id="audit-progress" style="display:none;margin-top:12px;color:#666;font-style:italic;">Đang phân tích...</div>
        <div id="audit-result" style="margin-top:20px;"></div>
        <?php
    }

    private function renderFimTab(): void
    {
        $status = \App\Settings\Security\FileIntegrityMonitor::getStatus();
        ?>
        <h2>Giám sát toàn vẹn file (FIM)</h2>
        <?php if ($status['has_baseline']): ?>
            <p>Baseline cuối: <strong><?php echo esc_html($status['baseline_time']); ?></strong>
               (<?php echo number_format($status['file_count']); ?> file)</p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
                <button id="btn-fim-scan" class="button button-primary">🔍 So sánh với baseline</button>
                <button id="btn-fim-update" class="button button-secondary">🔄 Cập nhật baseline</button>
            </div>
        <?php else: ?>
            <p>Chưa có baseline. Nhấn <strong>Tạo baseline</strong> để ghi lại trạng thái file hiện tại.</p>
            <button id="btn-fim-scan" class="button button-primary">📸 Tạo baseline</button>
        <?php endif; ?>
        <div id="fim-progress" style="display:none;margin-top:12px;color:#666;font-style:italic;">Đang quét...</div>
        <div id="fim-result" style="margin-top:20px;"></div>
        <?php
    }

    private function renderMalwareTab(): void
    {
        ?>
        <h2>Quét mã độc hại</h2>
        <p>Phân tích PHP, JS, HTML, SVG tìm backdoor, shell, obfuscated code...</p>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
            <div>
                <strong>Loại file:</strong>
                <?php foreach (['php' => 'PHP', 'js' => 'JavaScript', 'html' => 'HTML'] as $ext => $label): ?>
                    <label style="margin-right:10px;">
                        <input type="checkbox" name="scan_ext[]" value="<?php echo esc_attr($ext); ?>" <?php echo $ext === 'php' ? 'checked' : ''; ?>>
                        <?php echo esc_html($label); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <button id="btn-malware-scan" class="button button-primary">🦠 Bắt đầu quét</button>
        </div>
        <div id="malware-progress" style="display:none;margin-top:12px;">
            <div id="malware-progress-bar" style="height:6px;background:#2271b1;width:0%;border-radius:3px;transition:width 0.3s;"></div>
            <div id="malware-progress-text" style="margin-top:6px;color:#666;font-size:13px;"></div>
        </div>
        <div id="malware-result" style="margin-top:20px;"></div>
        <?php
    }

    private function renderUsersTab(): void
    {
        ?>
        <h2>Quét User Ẩn</h2>
        <p>Phát hiện tài khoản admin/user trong database nhưng bị ẩn khỏi màn hình Người dùng wp-admin.</p>
        <button id="btn-user-scan" class="button button-primary">🔍 Quét ngay</button>
        <div id="user-scan-progress" style="display:none;margin-top:12px;color:#666;font-style:italic;">Đang quét...</div>
        <div id="user-scan-result" style="margin-top:20px;"></div>
        <?php
    }

    private function renderLoginTab(): void
    {
        $slug    = get_option('laca_login_slug', '');
        $enabled = get_option('laca_enable_custom_login', 0);
        $homeUrl = trailingslashit(home_url());
        ?>
        <h2>Tùy chỉnh URL đăng nhập</h2>
        <p>Ẩn <code>/wp-login.php</code> và phục vụ trang đăng nhập qua URL tùy chỉnh. URL cũ trả 404.</p>
        <table class="form-table">
            <tr>
                <th>Bật tính năng</th>
                <td><label><input type="checkbox" id="laca-login-enabled" <?php checked($enabled, 1); ?>> Bật URL đăng nhập tùy chỉnh</label></td>
            </tr>
            <tr>
                <th>Slug đăng nhập</th>
                <td>
                    <span><?php echo esc_html($homeUrl); ?></span>
                    <input type="text" id="laca-login-slug" value="<?php echo esc_attr($slug); ?>"
                           style="width:200px;" placeholder="my-login">
                    <p class="description">⚠️ Lưu slug này cẩn thận — nếu quên sẽ không vào được admin!</p>
                </td>
            </tr>
        </table>
        <button id="btn-save-login" class="button button-primary">💾 Lưu cài đặt</button>
        <span id="login-save-msg" style="margin-left:10px;"></span>
        <div style="margin-top:20px;padding:14px;background:#fff3cd;border:1px solid #ffc107;border-radius:6px;">
            <strong>⚠️ Lưu ý quan trọng:</strong>
            <ul style="margin:8px 0 0 20px;">
                <li>Sau khi lưu, cài đặt chỉ có hiệu lực khi <strong>làm mới lại trang</strong> (do hook <code>plugins_loaded</code>).</li>
                <li>Ghi nhớ slug mới trước khi lưu. Nếu quên, vào DB xóa option <code>laca_login_slug</code>.</li>
                <li>URL hiện tại: <code><?php echo $slug ? esc_html($homeUrl . $slug) : '(chưa cài đặt)'; ?></code></li>
            </ul>
        </div>
        <?php
    }

    private function render2faTab(): void
    {
        $masterEnabled = get_option('laca_2fa_master_enabled', 0);
        ?>
        <h2>Xác thực 2 bước (2FA TOTP)</h2>
        <p>Bật 2FA toàn site — mỗi user có thể tự cài đặt trong trang Profile của mình.</p>
        <table class="form-table">
            <tr>
                <th>Bật 2FA toàn site</th>
                <td>
                    <label>
                        <input type="checkbox" id="laca-2fa-master" <?php checked($masterEnabled, 1); ?>>
                        Hiển thị tính năng 2FA trên trang Profile của tất cả user
                    </label>
                    <p class="description">Sau khi bật, mỗi user vào <a href="<?php echo esc_url(admin_url('profile.php')); ?>">Hồ sơ cá nhân</a> để kích hoạt 2FA riêng.</p>
                </td>
            </tr>
        </table>
        <button id="btn-save-2fa" class="button button-primary">💾 Lưu cài đặt</button>
        <span id="2fa-save-msg" style="margin-left:10px;"></span>

        <hr style="margin:24px 0;">
        <h3>Trạng thái 2FA người dùng</h3>
        <?php
        $users = get_users(['role__in' => ['administrator', 'editor'], 'number' => 50]);
        if ($users): ?>
        <table class="wp-list-table widefat fixed striped" style="max-width:600px;">
            <thead><tr><th>User</th><th>Vai trò</th><th>2FA</th></tr></thead>
            <tbody>
            <?php foreach ($users as $user):
                $enabled  = get_user_meta($user->ID, 'laca_2fa_enabled', true);
                $verified = get_user_meta($user->ID, 'laca_2fa_verified', true);
                $status   = ($enabled && $verified) ? '<span style="color:green;">✓ Đã bật</span>' : '<span style="color:#999;">— Chưa bật</span>';
            ?>
                <tr>
                    <td><?php echo esc_html($user->user_login); ?> <small style="color:#666;"><?php echo esc_html($user->user_email); ?></small></td>
                    <td><?php echo esc_html(implode(', ', $user->roles)); ?></td>
                    <td><?php echo $status; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?php
    }
}
