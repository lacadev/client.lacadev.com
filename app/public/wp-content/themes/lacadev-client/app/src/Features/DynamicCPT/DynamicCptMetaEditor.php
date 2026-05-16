<?php

namespace App\Features\DynamicCPT;

use App\Features\DynamicCPT\Meta\MetaCodeGenerator;
use App\Features\DynamicCPT\Meta\MetaEditorRenderer;

/**
 * DynamicCptMetaEditor
 *
 * Cung cấp 2 luồng để định nghĩa meta fields cho Dynamic CPT:
 *  1. Field Builder  — UI đơn giản → generate PHP code
 *  2. Code Editor    — chỉnh sửa trực tiếp PHP (full Carbon Fields API)
 *
 * File được lưu tại: app/src/PostTypes/DynamicMeta/{slug}-meta.php
 * DynamicCptManager sẽ require_once file này trên mỗi request.
 *
 * Options được hỗ trợ theo Carbon Fields docs (https://docs.carbonfields.net):
 *  - Tất cả types : default_value, help_text, required, width
 *  - text/textarea/date : placeholder (set_attribute)
 *  - textarea   : rows
 *  - select     : options (value|Label per line)
 *  - checkbox   : option_value
 *  - image/file : value_type (id|url), file_type (file only)
 *  - color      : alpha_enabled, palette (comma-separated hex)
 *  - date       : storage_format
 */
class DynamicCptMetaEditor
{
    const NONCE_ACTION = 'laca_cpt_meta_action';
    const NONCE_FIELD  = '_laca_meta_nonce';
    const CAP          = 'manage_options';

    private string $metaDir;

    public function __construct()
    {
        $this->metaDir = DynamicCptManager::getMetaDir();

        add_action('admin_post_laca_cpt_meta_save',     [$this, 'handleSave']);
        add_action('admin_post_laca_cpt_meta_generate', [$this, 'handleGenerate']);
    }

    // -------------------------------------------------------------------------
    // Public API — dùng bởi DynamicCptManager và DynamicCptAdminPage
    // -------------------------------------------------------------------------

    public function getMetaFilePath(string $slug): string
    {
        return $this->metaDir . '/' . sanitize_key($slug) . '-meta.php';
    }

    public function metaFileExists(string $slug): bool
    {
        return file_exists($this->getMetaFilePath($slug));
    }

    public function getMetaFileContent(string $slug): string
    {
        $file = $this->getMetaFilePath($slug);
        if (file_exists($file)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            return file_get_contents($file);
        }
        return $this->generateStub($slug, 'Thông tin ' . $slug, []);
    }

    public function deleteMetaFile(string $slug): void
    {
        $file = $this->getMetaFilePath($slug);
        if (file_exists($file)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
            unlink($file);
        }
    }

    public function saveMetaFile(string $slug, string $code): bool
    {
        if (!is_dir($this->metaDir)) {
            wp_mkdir_p($this->metaDir);
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        return file_put_contents($this->getMetaFilePath($slug), $code) !== false;
    }

    // -------------------------------------------------------------------------
    // Code Generation
    // -------------------------------------------------------------------------

    public function generateStub(string $slug, string $containerTitle, array $fields): string
    {
        return (new MetaCodeGenerator())->generateStub($slug, $containerTitle, $fields);
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

        $slug = sanitize_key($_POST['cpt_slug'] ?? '');
        if (!$slug || !$this->isKnownSlug($slug)) {
            wp_die(esc_html__('Invalid slug.', 'laca'));
        }

        $code = wp_unslash($_POST['meta_code'] ?? '');
        $this->saveMetaFile($slug, $code);

        wp_safe_redirect(admin_url(
            'admin.php?page=laca-dynamic-cpt&meta=' . $slug . '&laca_meta_msg=saved'
        ));
        exit;
    }

    public function handleGenerate(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Permission denied.', 'laca'));
        }
        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $slug  = sanitize_key($_POST['cpt_slug'] ?? '');
        $title = sanitize_text_field($_POST['container_title'] ?? ('Thông tin ' . $slug));

        if (!$slug || !$this->isKnownSlug($slug)) {
            wp_die(esc_html__('Invalid slug.', 'laca'));
        }

        $fields = [];
        foreach ((array)($_POST['meta_fields'] ?? []) as $f) {
            $name = sanitize_key($f['name'] ?? '');
            if (!$name) {
                continue;
            }
            $fields[] = [
                'name'           => $name,
                'label'          => sanitize_text_field($f['label']          ?? ''),
                'type'           => sanitize_key($f['type']                  ?? 'text'),
                'width'          => absint($f['width']                       ?? 100),
                'placeholder'    => sanitize_text_field($f['placeholder']    ?? ''),
                'default_value'  => sanitize_text_field($f['default_value']  ?? ''),
                'help_text'      => sanitize_text_field($f['help_text']      ?? ''),
                'required'       => !empty($f['required']),
                // textarea
                'rows'           => absint($f['rows']                        ?? 5),
                // select
                'options'        => sanitize_textarea_field($f['options']    ?? ''),
                // checkbox
                'option_value'   => sanitize_text_field($f['option_value']   ?? ''),
                // image / file
                'value_type'     => sanitize_key($f['value_type']            ?? 'id'),
                'file_type'      => sanitize_text_field($f['file_type']      ?? ''),
                // color
                'alpha_enabled'  => !empty($f['alpha_enabled']),
                'palette'        => sanitize_text_field($f['palette']        ?? ''),
                // date
                'storage_format' => sanitize_text_field($f['storage_format'] ?? ''),
            ];
        }

        $code = $this->generateStub($slug, $title, $fields);
        $this->saveMetaFile($slug, $code);

        wp_safe_redirect(admin_url(
            'admin.php?page=laca-dynamic-cpt&meta=' . $slug . '&laca_meta_msg=generated'
        ));
        exit;
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------

    public function renderMetaEditor(string $slug, array $cpt): void
    {
        $code     = $this->getMetaFileContent($slug);
        $filePath = $this->getMetaFilePath($slug);
        $exists   = file_exists($filePath);
        $pageUrl  = admin_url('admin.php?page=laca-dynamic-cpt');
        $msgType  = sanitize_key($_GET['laca_meta_msg'] ?? '');

        $messages = [
            'saved'     => __('Đã lưu code thành công.', 'laca'),
            'generated' => __('Đã generate code từ Field Builder. Kiểm tra và lưu nếu đúng.', 'laca'),
        ];
        $message = $messages[$msgType] ?? '';

        $cmSettings = wp_enqueue_code_editor(['type' => 'application/x-httpd-php']);
        wp_enqueue_script('wp-theme-plugin-editor');
        wp_enqueue_style('wp-codemirror');
        (new MetaEditorRenderer())->render(
            $slug,
            $cpt,
            $code,
            $filePath,
            $exists,
            $pageUrl,
            $message,
            $cmSettings,
            self::NONCE_ACTION,
            self::NONCE_FIELD
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function isKnownSlug(string $slug): bool
    {
        return \in_array($slug, array_column(DynamicCptManager::getAll(), 'slug'), true);
    }
}
