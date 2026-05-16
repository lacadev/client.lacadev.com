<?php

namespace App\Settings\Security;

use App\Settings\Security\Admin\FimResultRenderer;
use App\Settings\Security\Admin\InlineAssets;
use App\Settings\Security\Admin\PageRenderer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SecurityManager
 *
 * Trang admin tổng hợp bảo mật (Appearance > 🔒 Bảo mật).
 * Đăng ký toàn bộ AJAX endpoints cho 6 tính năng bảo mật.
 *
 * Tabs:
 *   1. Kiểm tra bảo mật (Security Audit)
 *   2. Giám sát file (FIM)
 *   3. Quét mã độc (Malware Scanner)
 *   4. User ẩn (Hidden User Scanner)
 *   5. URL đăng nhập (Custom Login)
 *   6. 2FA
 */
class SecurityManager
{
    private const NONCE = 'laca_security_nonce';

    public function init(): void
    {
        add_action('admin_menu',            [$this, 'addMenu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

        // ── AJAX: Security Audit ───────────────────────────────────────────────
        add_action('wp_ajax_laca_security_audit',        [$this, 'ajaxAudit']);

        // ── AJAX: FIM ─────────────────────────────────────────────────────────
        add_action('wp_ajax_laca_fim_scan',              [$this, 'ajaxFimScan']);
        add_action('wp_ajax_laca_fim_update_baseline',   [$this, 'ajaxFimUpdateBaseline']);

        // ── AJAX: Malware Scanner ─────────────────────────────────────────────
        add_action('wp_ajax_laca_malware_init',          [$this, 'ajaxMalwareInit']);
        add_action('wp_ajax_laca_malware_chunk',         [$this, 'ajaxMalwareChunk']);
        add_action('wp_ajax_laca_malware_result',        [$this, 'ajaxMalwareResult']);

        // ── AJAX: Hidden User Scanner ─────────────────────────────────────────
        add_action('wp_ajax_laca_hidden_user_scan',      [$this, 'ajaxHiddenUserScan']);

        // ── AJAX: Custom Login Settings ───────────────────────────────────────
        add_action('wp_ajax_laca_save_login_settings',   [$this, 'ajaxSaveLoginSettings']);

        // ── AJAX: 2FA Master Toggle ────────────────────────────────────────────
        add_action('wp_ajax_laca_save_2fa_settings',     [$this, 'ajaxSave2faSettings']);
    }

    // ── Admin Menu ───────────────────────────────────────────────────────────

    public function addMenu(): void
    {
        add_submenu_page(
            'laca-admin',
            'Bảo mật',
            'Bảo mật',
            'manage_options',
            'laca-security',
            [$this, 'renderPage']
        );
    }

    // ── Enqueue ──────────────────────────────────────────────────────────────

    public function enqueueAssets(string $hook): void
    {
        if (!str_contains($hook, 'laca-security')) return;
        wp_add_inline_script('jquery', 'var lacaSecurity = ' . wp_json_encode([
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::NONCE),
        ]) . ';', 'before');
    }

    // ── Admin Page ────────────────────────────────────────────────────────────

    public function renderPage(): void
    {
        $activeTab = sanitize_key($_GET['tab'] ?? 'audit');
        $tabs = [
            'audit'   => 'Kiểm tra bảo mật',
            'fim'     => 'Giám sát file',
            'malware' => 'Quét mã độc',
            'users'   => 'User ẩn',
            'login'   => 'URL đăng nhập',
            '2fa'     => '2FA TOTP',
        ];

        if (!isset($tabs[$activeTab])) {
            $activeTab = 'audit';
        }
        (new PageRenderer())->renderPage($activeTab, $tabs);
        (new InlineAssets())->render();
    }

    // ── AJAX Handlers ────────────────────────────────────────────────────────

    private function checkNonce(): void
    {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }
    }

    public function ajaxAudit(): void
    {
        $this->checkNonce();
        wp_send_json_success(SecurityAudit::run());
    }

    // FIM

    public function ajaxFimScan(): void
    {
        $this->checkNonce();
        @set_time_limit(120);
        $result = FileIntegrityMonitor::compareBaseline();
        if (!empty($result['needs_init'])) {
            $init = FileIntegrityMonitor::createBaseline();
            wp_send_json_success([
                'is_init'   => true,
                'html'      => '<div class="notice notice-success inline"><p>✓ Baseline đã được tạo với <strong>' . number_format($init['total']) . '</strong> file.</p></div>',
                'base_time' => $init['time'],
                'total'     => 0,
            ]);
        }
        wp_send_json_success([
            'is_init'   => false,
            'html'      => (new FimResultRenderer())->render($result),
            'base_time' => $result['base_time'],
            'total'     => $result['total'],
        ]);
    }

    public function ajaxFimUpdateBaseline(): void
    {
        $this->checkNonce();
        @set_time_limit(120);
        $result = FileIntegrityMonitor::updateBaseline();
        wp_send_json_success([
            'message'   => '✓ Baseline đã cập nhật với ' . number_format($result['total']) . ' file.',
            'base_time' => $result['time'],
        ]);
    }

    // Malware

    public function ajaxMalwareInit(): void
    {
        $this->checkNonce();
        $exts = isset($_POST['extensions']) ? array_map('sanitize_text_field', (array) $_POST['extensions']) : ['php'];
        if (empty($exts)) $exts = ['php'];
        $files  = MalwareScanner::getFileList($exts);
        $scanId = 'laca_scan_' . uniqid();
        set_transient($scanId . '_files',    $files,  600);
        set_transient($scanId . '_findings', [],      600);
        wp_send_json_success(['scan_id' => $scanId, 'total' => count($files)]);
    }

    public function ajaxMalwareChunk(): void
    {
        $this->checkNonce();
        $scanId   = sanitize_text_field($_POST['scan_id'] ?? '');
        $offset   = (int) ($_POST['offset'] ?? 0);
        $files    = get_transient($scanId . '_files');
        $findings = get_transient($scanId . '_findings');
        if ($files === false) wp_send_json_error('Phiên đã hết hạn. Vui lòng thử lại.');

        $chunk    = MalwareScanner::scanChunk($files, $offset, 50);
        $findings = array_merge($findings, $chunk);
        set_transient($scanId . '_findings', $findings, 600);

        $next  = $offset + 50;
        $total = count($files);
        wp_send_json_success([
            'done'        => $next >= $total,
            'next_offset' => $next,
            'scanned'     => min($next, $total),
            'total'       => $total,
            'findings'    => count($findings),
        ]);
    }

    public function ajaxMalwareResult(): void
    {
        $this->checkNonce();
        $scanId   = sanitize_text_field($_POST['scan_id'] ?? '');
        $findings = get_transient($scanId . '_findings');
        $files    = get_transient($scanId . '_files');
        if ($findings === false) wp_send_json_error('Phiên đã hết hạn.');

        $scanned = is_array($files) ? count($files) : 0;
        wp_send_json_success([
            'html'     => MalwareScanner::renderResults($findings, $scanned),
            'total'    => count($findings),
            'scan_id'  => $scanId,
        ]);
    }

    // Hidden Users

    public function ajaxHiddenUserScan(): void
    {
        $this->checkNonce();
        try {
            $scanner = new HiddenUserScanner();
            wp_send_json_success($scanner->scan());
        } catch (\Exception $e) {
            wp_send_json_error('Quét thất bại: ' . $e->getMessage());
        }
    }

    // Custom Login Settings

    public function ajaxSaveLoginSettings(): void
    {
        $this->checkNonce();
        $slug    = sanitize_title(trim(sanitize_text_field($_POST['slug'] ?? ''), '/'));
        $enabled = !empty($_POST['enabled']) ? 1 : 0;
        if ($enabled && empty($slug)) {
            wp_send_json_error('Slug không được để trống khi bật tính năng.');
        }
        update_option('laca_login_slug',          $slug);
        update_option('laca_enable_custom_login', $enabled);
        wp_send_json_success('Cài đặt đã lưu. Tải lại trang để áp dụng.');
    }

    // 2FA Master Setting

    public function ajaxSave2faSettings(): void
    {
        $this->checkNonce();
        $enabled = !empty($_POST['enabled']) ? 1 : 0;
        update_option('laca_2fa_master_enabled', $enabled);
        wp_send_json_success($enabled ? '2FA đã bật toàn site.' : '2FA đã tắt.');
    }
}
