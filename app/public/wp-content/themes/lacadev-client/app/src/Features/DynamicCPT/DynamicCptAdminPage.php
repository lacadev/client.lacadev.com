<?php

namespace App\Features\DynamicCPT;

use App\Features\DynamicCPT\Admin\AdminPageRenderer;

/**
 * DynamicCptAdminPage
 *
 * Tạo trang admin tại Laca Admin > Custom Post Types.
 * Cho phép thêm / sửa / xoá CPT động, hỗ trợ taxonomy category, tag, custom.
 * Khi tạo mới sẽ tự sinh archive-{slug}.php + single-{slug}.php.
 */
class DynamicCptAdminPage
{
    const NONCE_ACTION = 'laca_dynamic_cpt_action';
    const NONCE_FIELD  = '_laca_cpt_nonce';
    const CAP          = 'manage_options';
    const MENU_SLUG    = 'laca-dynamic-cpt';
    const PARENT_SLUG  = 'laca-admin';

    private DynamicCptMetaEditor $metaEditor;

    public function __construct()
    {
        $this->metaEditor = new DynamicCptMetaEditor();

        add_action('admin_menu', [$this, 'registerMenu'], 20);
        add_action('admin_post_laca_cpt_save',   [$this, 'handleSave']);
        add_action('admin_post_laca_cpt_delete', [$this, 'handleDelete']);
        add_action('admin_post_laca_cpt_regen',  [$this, 'handleRegen']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            self::PARENT_SLUG,
            __('Custom Post Types', 'laca'),
            __('Custom Post Types', 'laca'),
            self::CAP,
            self::MENU_SLUG,
            [$this, 'renderPage']
        );
    }

    // -------------------------------------------------------------------------
    // Page Render
    // -------------------------------------------------------------------------

    public function renderPage(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'laca'));
        }

        // Route: nếu có ?meta=slug thì hiện meta editor thay vì list
        if (isset($_GET['meta'])) {
            $metaSlug = sanitize_key($_GET['meta']);
            $cpts     = DynamicCptManager::getAll();
            $cptData  = [];
            foreach ($cpts as $cpt) {
                if (($cpt['slug'] ?? '') === $metaSlug) {
                    $cptData = $cpt;
                    break;
                }
            }
            $this->metaEditor->renderMetaEditor($metaSlug, $cptData);
            return;
        }

        $cpts       = DynamicCptManager::getAll();
        $editing    = null;
        $edit_index = -1;

        if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
            $idx = absint($_GET['edit']);
            if (isset($cpts[$idx])) {
                $editing    = $cpts[$idx];
                $edit_index = $idx;
            }
        }

        $message   = $this->getFlashMessage();
        $msg_type  = sanitize_key($_GET['laca_cpt_msg'] ?? '');
        $page_url  = admin_url('admin.php?page=' . self::MENU_SLUG);
        (new AdminPageRenderer())->render(
            $cpts,
            $editing,
            $edit_index,
            $message,
            $msg_type,
            $page_url,
            $this->metaEditor,
            self::NONCE_ACTION,
            self::NONCE_FIELD
        );
    }

    // -------------------------------------------------------------------------
    // POST Handlers
    // -------------------------------------------------------------------------

    public function handleSave(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Permission denied.', 'laca'));
        }

        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $slug     = sanitize_key($_POST['cpt_slug']     ?? '');
        $url_slug = sanitize_title($_POST['cpt_url_slug'] ?? '');
        $singular = sanitize_text_field($_POST['cpt_singular'] ?? '');
        $plural   = sanitize_text_field($_POST['cpt_plural']   ?? '');
        $icon     = sanitize_text_field($_POST['cpt_icon']     ?? 'dashicons-admin-post');
        $supports = array_map('sanitize_key', (array)($_POST['cpt_supports'] ?? ['title', 'editor']));
        $index    = (int)($_POST['cpt_index'] ?? -1);

        $page_url = admin_url('admin.php?page=' . self::MENU_SLUG);

        if (!$slug || !$singular || !$plural) {
            wp_redirect(add_query_arg('laca_cpt_msg', 'error', $page_url));
            exit;
        }

        // Bảo vệ reserved slugs
        $reserved = ['post', 'page', 'attachment', 'revision', 'nav_menu_item', 'action', 'order', 'theme'];
        if (in_array($slug, $reserved, true)) {
            wp_redirect(add_query_arg('laca_cpt_msg', 'exists', $page_url));
            exit;
        }

        $taxonomies = [
            'category' => !empty($_POST['tax_category']),
            'tag'      => !empty($_POST['tax_tag']),
            'custom'   => [],
        ];

        foreach ((array)($_POST['tax_custom'] ?? []) as $tax) {
            $tax_slug = sanitize_key($tax['slug'] ?? '');
            if (!$tax_slug) {
                continue;
            }
            $taxonomies['custom'][] = [
                'slug'         => $tax_slug,
                'singular'     => sanitize_text_field($tax['singular'] ?? $tax_slug),
                'plural'       => sanitize_text_field($tax['plural']   ?? $tax_slug),
                'hierarchical' => !empty($tax['hierarchical']),
            ];
        }

        $cpt_data = [
            'slug'       => $slug,
            'url_slug'   => $url_slug,
            'singular'   => $singular,
            'plural'     => $plural,
            'menu_icon'  => $icon,
            'supports'   => $supports,
            'taxonomies' => $taxonomies,
        ];

        $cpts    = DynamicCptManager::getAll();
        $is_new  = ($index < 0 || !isset($cpts[$index]));

        if (!$is_new) {
            // Giữ nguyên slug gốc khi edit
            $cpt_data['slug'] = sanitize_key($cpts[$index]['slug']);
            $cpts[$index]     = $cpt_data;
        } else {
            // Kiểm tra slug trùng
            foreach ($cpts as $existing) {
                if (($existing['slug'] ?? '') === $slug) {
                    wp_redirect(add_query_arg('laca_cpt_msg', 'exists', $page_url));
                    exit;
                }
            }
            $cpts[] = $cpt_data;
            (new DynamicCptTemplateGenerator())->generate($slug);
        }

        DynamicCptManager::saveAll($cpts);
        flush_rewrite_rules();

        wp_redirect(add_query_arg('laca_cpt_msg', 'saved', $page_url));
        exit;
    }

    public function handleRegen(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Permission denied.', 'laca'));
        }

        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $slug     = sanitize_key($_POST['cpt_slug'] ?? '');
        $page_url = admin_url('admin.php?page=' . self::MENU_SLUG);

        if (!$slug) {
            wp_redirect(add_query_arg('laca_cpt_msg', 'error', $page_url));
            exit;
        }

        // Chỉ sinh nếu slug tồn tại trong danh sách CPT đã đăng ký
        $known = array_column(DynamicCptManager::getAll(), 'slug');
        if (!in_array($slug, $known, true)) {
            wp_redirect(add_query_arg('laca_cpt_msg', 'error', $page_url));
            exit;
        }

        $result   = (new DynamicCptTemplateGenerator())->generate($slug);
        $all_ok   = $result['archive'] && $result['single'];

        wp_redirect(add_query_arg('laca_cpt_msg', $all_ok ? 'regen_ok' : 'regen_fail', $page_url));
        exit;
    }

    public function handleDelete(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Permission denied.', 'laca'));
        }

        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $index = absint($_POST['cpt_index'] ?? 0);
        $cpts  = DynamicCptManager::getAll();
        $page_url = admin_url('admin.php?page=' . self::MENU_SLUG);

        if (!isset($cpts[$index])) {
            wp_redirect(add_query_arg('laca_cpt_msg', 'error', $page_url));
            exit;
        }

        $slug = sanitize_key($cpts[$index]['slug'] ?? '');
        array_splice($cpts, $index, 1);
        DynamicCptManager::saveAll($cpts);

        if ($slug) {
            (new DynamicCptTemplateGenerator())->delete($slug);
            $this->metaEditor->deleteMetaFile($slug);
        }

        flush_rewrite_rules();

        wp_redirect(add_query_arg('laca_cpt_msg', 'deleted', $page_url));
        exit;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function getFlashMessage(): string
    {
        if (!isset($_GET['laca_cpt_msg'])) {
            return '';
        }

        $map = [
            'saved'      => __('Post type đã được lưu. Template archive/single được tạo tự động.', 'laca'),
            'deleted'    => __('Post type đã bị xoá.', 'laca'),
            'exists'     => __('Slug này đã tồn tại, vui lòng chọn slug khác.', 'laca'),
            'error'      => __('Có lỗi xảy ra, vui lòng thử lại.', 'laca'),
            'regen_ok'   => __('Template archive & single đã được sinh thành công!', 'laca'),
            'regen_fail' => __('Không thể sinh template — kiểm tra quyền ghi vào thư mục theme.', 'laca'),
        ];

        $key = sanitize_key($_GET['laca_cpt_msg']);
        return $map[$key] ?? '';
    }
}
