<?php

namespace App\Features\ContactForm;

use App\Databases\ContactFormTable;

/**
 * ContactFormManager
 *
 * Admin UI để tạo và quản lý form liên hệ tùy chỉnh.
 * Menu: Appearance > Form Liên Hệ
 *
 * Views:
 *   (default)             → danh sách tất cả forms
 *   ?action=new           → tạo form mới
 *   ?action=edit&id=X     → sửa form
 *   ?action=submissions&id=X → xem submissions
 *
 * Data format (fields column in DB) — row-based:
 *   [ { id, cols: [ { id, span, fields: [ {id, type, name, label, ...} ] } ] } ]
 * Old flat format still supported for display/submissions.
 */
class ContactFormManager
{
    const NONCE_ACTION = 'laca_contact_form_action';
    const NONCE_FIELD = '_laca_cf_nonce';
    const CAP = 'manage_options';
    const MENU_SLUG = 'laca-contact-forms';
    const PARENT_SLUG = 'laca-admin';

    /** Field types được hỗ trợ */
    const FIELD_TYPES = [
        'text' => 'Văn bản (Text)',
        'textarea' => 'Đoạn văn (Textarea)',
        'email' => 'Email',
        'phone' => 'Số điện thoại',
        'number' => 'Số (Number)',
        'select' => 'Dropdown (Select)',
        'multiselect' => 'Chọn nhiều (Multi-select)',
        'radio' => 'Radio button',
        'checkbox' => 'Checkbox',
        'date' => 'Ngày (Date)',
        'datetime' => 'Ngày & Giờ (Datetime)',
        'url' => 'Đường dẫn (URL)',
        'hidden' => 'Ẩn (Hidden)',
        'step_break' => 'Ngắt bước (Step)',
    ];

    /** Allowed column spans in 12-col grid */
    const ALLOWED_SPANS = [3, 4, 6, 8, 12];

    public function __construct()
    {
        add_action('admin_init', ['App\Databases\ContactFormTable', 'install']);
        add_action('admin_menu', [$this, 'registerMenu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_post_laca_cf_save', [$this, 'handleSave']);
        add_action('admin_post_laca_cf_delete', [$this, 'handleDelete']);
        add_action('admin_post_laca_cf_delete_submission', [$this, 'handleDeleteSubmission']);
        add_action('admin_post_laca_cf_mark_read', [$this, 'handleMarkRead']);
        add_action('admin_post_laca_cf_export_csv', [$this, 'handleExportCsv']);
    }

    public function enqueueAssets(string $hook): void
    {
        if (!str_contains($hook, self::MENU_SLUG)) {
            return;
        }

        $action = sanitize_key($_GET['action'] ?? '');
        if (!in_array($action, ['new', 'edit', ''], true)) {
            return;
        }

        $themeRoot = dirname(get_template_directory());
        $themeRootUri = dirname(get_template_directory_uri());
        $sortableFile = $themeRoot . '/node_modules/sortablejs/Sortable.min.js';
        $sortableUrl = $themeRootUri . '/node_modules/sortablejs/Sortable.min.js';

        if (file_exists($sortableFile)) {
            wp_enqueue_script('sortablejs', $sortableUrl, [], '1.15.7', false);
        }
    }

    // =========================================================================
    // MENU
    // =========================================================================

    public function registerMenu(): void
    {
        add_submenu_page(
            self::PARENT_SLUG,
            __('Form Liên Hệ', 'laca'),
            __('Form Liên Hệ', 'laca'),
            self::CAP,
            self::MENU_SLUG,
            [$this, 'renderPage']
        );
    }

    // =========================================================================
    // PAGE ROUTER
    // =========================================================================

    public function renderPage(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Bạn không có quyền truy cập trang này.', 'laca'));
        }

        $action = sanitize_key($_GET['action'] ?? '');
        $id = absint($_GET['id'] ?? 0);

        switch ($action) {
            case 'new':
                $this->renderEditPage(null);
                break;
            case 'edit':
                $form = $id ? ContactFormTable::getForm($id) : null;
                if (!$form) {
                    wp_die(esc_html__('Form không tồn tại.', 'laca'));
                }
                $this->renderEditPage($form);
                break;
            case 'submissions':
                $form = $id ? ContactFormTable::getForm($id) : null;
                if (!$form) {
                    wp_die(esc_html__('Form không tồn tại.', 'laca'));
                }
                $this->renderSubmissionsPage($form);
                break;
            default:
                $this->renderListPage();
        }
    }

    // =========================================================================
    // HELPERS — extract flat field list from either DB format
    // =========================================================================

    /**
     * Extract a flat array of field objects from a form row.
     * Handles both old flat format and new row-based format.
     */
    private static function extractFlatFields(array $form): array
    {
        return ContactFormSchema::extractFlatFields($form);
    }

    /**
     * Convert raw DB data to row-based format for the builder JS.
     * Old flat format is auto-converted: each field → single-col row.
     */
    private static function toRowsFormat(array $form): array
    {
        return ContactFormSchema::toRowsFormat($form, self::ALLOWED_SPANS);
    }

    // =========================================================================
    // DEFAULT CONTENT
    // =========================================================================

    /**
     * Default fields cho form mới — giống template-contact.php
     */
    private static function defaultFormRows(): array
    {
        return ContactFormDefaults::rows();
    }

    /**
     * Default HTML email body gửi Admin
     */
    private static function defaultAdminEmailBody(): string
    {
        return ContactFormDefaults::adminEmailBody();
    }

    /**
     * Default HTML email body xác nhận gửi Khách hàng
     */
    private static function defaultCustomerEmailBody(): string
    {
        return ContactFormDefaults::customerEmailBody(get_bloginfo('name'));
    }

    // =========================================================================
    // LIST PAGE
    // =========================================================================

    private function renderListPage(): void
    {
        $forms = ContactFormTable::getAllForms();
        $pageUrl = admin_url('admin.php?page=' . self::MENU_SLUG);
        $items = [];

        foreach ($forms as $form) {
            $formId = (int) $form['id'];
            $items[] = [
                'id' => $formId,
                'name' => (string) $form['name'],
                'field_count' => count(self::extractFlatFields($form)),
                'submission_count' => (int) $form['submission_count'],
                'unread_count' => (int) $form['unread_count'],
                'shortcode' => '[laca_contact_form id="' . $formId . '"]',
                'edit_url' => $pageUrl . '&action=edit&id=' . $formId,
                'submissions_url' => $pageUrl . '&action=submissions&id=' . $formId,
                'delete_action' => admin_url('admin-post.php'),
            ];
        }

        echo ContactFormListPageRenderer::render([
            'page_url' => $pageUrl,
            'message' => $this->getFlashMessage(),
            'items' => $items,
            'nonce_action' => self::NONCE_ACTION,
            'nonce_field' => self::NONCE_FIELD,
        ]);
    }

    // =========================================================================
    // EDIT / NEW FORM PAGE
    // =========================================================================

    private function renderEditPage(?array $form): void
    {
        $isNew = ($form === null);
        $pageUrl = admin_url('admin.php?page=' . self::MENU_SLUG);
        $formId = $isNew ? 0 : (int) $form['id'];
        $rows = $isNew ? self::defaultFormRows() : self::toRowsFormat($form);
        $defaultAdminSubject = 'Liên hệ mới: [$name - $phone_number]';
        $defaultAdminBody = self::defaultAdminEmailBody();
        $defaultCustomerSubject = 'Cảm ơn bạn đã liên hệ - ' . get_bloginfo('name');
        $defaultCustomerBody = self::defaultCustomerEmailBody();
        $adminEmail = (string) get_option('admin_email');
        $useAdminNotifyEmail = empty($form['notify_email']);
        echo ContactFormEditPageRenderer::render([
            'is_new' => $isNew,
            'page_url' => $pageUrl,
            'form_action' => admin_url('admin-post.php'),
            'form_id' => $formId,
            'form_name' => (string) ($form['name'] ?? ''),
            'rows' => $rows,
            'style_json' => (string) ($form['style_settings'] ?? '{}'),
            'message' => $this->getFlashMessage(),
            'notify_email' => (string) ($form['notify_email'] ?? ''),
            'admin_email' => $adminEmail,
            'use_admin_notify_email' => $useAdminNotifyEmail,
            'email_admin_subject' => (string) ($form['email_admin_subject'] ?? $defaultAdminSubject),
            'email_admin_body' => (string) ($form['email_admin_body'] ?? $defaultAdminBody),
            'email_customer_subject' => (string) ($form['email_customer_subject'] ?? $defaultCustomerSubject),
            'email_customer_body' => (string) ($form['email_customer_body'] ?? $defaultCustomerBody),
            'field_types' => self::FIELD_TYPES,
            'nonce_action' => self::NONCE_ACTION,
            'nonce_field' => self::NONCE_FIELD,
        ]);
    }

    // =========================================================================
    // SUBMISSIONS PAGE
    // =========================================================================

    private function renderSubmissionsPage(array $form): void
    {
        $formId  = (int) $form['id'];
        $pageUrl = admin_url('admin.php?page=' . self::MENU_SLUG);
        $page    = max(1, absint($_GET['paged'] ?? 1));
        $perPage = 20;
        $subs    = ContactFormTable::getSubmissions($formId, $page, $perPage);
        $total   = ContactFormTable::countSubmissions($formId);
        $pages   = (int) ceil($total / $perPage);
        $fields  = self::extractFlatFields($form);
        $items = [];

        foreach ($subs as $sub) {
            $data = json_decode($sub['data'] ?? '{}', true) ?: [];
            $values = [];

            foreach ($fields as $field) {
                $value = $data[$field['name']] ?? '';
                $values[] = is_array($value) ? implode(', ', $value) : (string) $value;
            }

            $subId = (int) $sub['id'];
            $items[] = [
                'id' => $subId,
                'is_read' => (bool) $sub['is_read'],
                'values' => $values,
                'ip_address' => (string) $sub['ip_address'],
                'created_at' => date_i18n('d/m/Y H:i', strtotime($sub['created_at'])),
                'mark_url' => admin_url('admin-post.php?action=laca_cf_mark_read&submission_id=' . $subId . '&form_id=' . $formId . '&' . self::NONCE_FIELD . '=' . wp_create_nonce(self::NONCE_ACTION)),
                'delete_url' => admin_url('admin-post.php?action=laca_cf_delete_submission&submission_id=' . $subId . '&form_id=' . $formId . '&' . self::NONCE_FIELD . '=' . wp_create_nonce(self::NONCE_ACTION)),
            ];
        }

        echo ContactFormSubmissionsPageRenderer::render([
            'form_id' => $formId,
            'form_name' => (string) $form['name'],
            'page_url' => $pageUrl,
            'total' => $total,
            'message' => $this->getFlashMessage(),
            'fields' => $fields,
            'items' => $items,
            'pages' => $pages,
            'page' => $page,
            'export_url' => $total > 0
                ? wp_nonce_url(
                    admin_url('admin-post.php?action=laca_cf_export_csv&form_id=' . $formId),
                    self::NONCE_ACTION,
                    self::NONCE_FIELD
                )
                : '',
        ]);
    }

    // =========================================================================
    // ACTION HANDLERS
    // =========================================================================

    public function handleSave(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Không có quyền.', 'laca'));
        }
        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $formId = absint($_POST['form_id'] ?? 0);
        $formName = sanitize_text_field($_POST['form_name'] ?? '');

        if (!$formName) {
            wp_redirect($this->buildRedirectUrl($formId, 'error_name'));
            exit;
        }

        $fieldsJson = stripslashes($_POST['fields_json'] ?? '[]');
        $rawData = json_decode($fieldsJson, true) ?: [];

        // Sanitize row-based structure.
        $cleanRows = ContactFormAdminSanitizer::rows($rawData, self::FIELD_TYPES, self::ALLOWED_SPANS);

        // Parse + sanitize style_json
        $styleJson = stripslashes($_POST['style_json'] ?? '{}');
        $rawStyle = json_decode($styleJson, true) ?: [];
        $cleanStyle = ContactFormAdminSanitizer::style($rawStyle);

        $data = [
            'name' => $formName,
            'fields' => $cleanRows,
            'notify_email' => !empty($_POST['use_admin_notify_email'])
                ? ''
                : sanitize_email($_POST['notify_email'] ?? ''),
            'email_admin_subject' => sanitize_text_field($_POST['email_admin_subject'] ?? ''),
            'email_admin_body' => wp_kses_post(stripslashes($_POST['email_admin_body'] ?? '')),
            'email_customer_subject' => sanitize_text_field($_POST['email_customer_subject'] ?? ''),
            'email_customer_body' => wp_kses_post(stripslashes($_POST['email_customer_body'] ?? '')),
            'style_settings' => $cleanStyle,
        ];

        if ($formId > 0) {
            ContactFormTable::updateForm($formId, $data);
            $redirectId = $formId;
        } else {
            $redirectId = ContactFormTable::insertForm($data);
        }

        wp_redirect($this->buildRedirectUrl($redirectId, 'saved'));
        exit;
    }

    public function handleDelete(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Không có quyền.', 'laca'));
        }
        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $formId = absint($_POST['form_id'] ?? 0);
        if ($formId > 0) {
            ContactFormTable::deleteForm($formId);
        }

        wp_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&laca_msg=deleted'));
        exit;
    }

    public function handleDeleteSubmission(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Không có quyền.', 'laca'));
        }
        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $subId = absint($_GET['submission_id'] ?? 0);
        $formId = absint($_GET['form_id'] ?? 0);
        if ($subId > 0) {
            ContactFormTable::deleteSubmission($subId);
        }

        wp_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&action=submissions&id=' . $formId . '&laca_msg=deleted'));
        exit;
    }

    public function handleMarkRead(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Không có quyền.', 'laca'));
        }
        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $subId = absint($_GET['submission_id'] ?? 0);
        $formId = absint($_GET['form_id'] ?? 0);
        if ($subId > 0) {
            ContactFormTable::markRead($subId);
        }

        wp_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&action=submissions&id=' . $formId . '&laca_msg=marked_read'));
        exit;
    }

    public function handleExportCsv(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Không có quyền.', 'laca'));
        }
        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $formId = absint($_GET['form_id'] ?? 0);
        $form = $formId ? ContactFormTable::getForm($formId) : null;
        if (!$form) {
            wp_die(esc_html__('Form không tồn tại.', 'laca'));
        }

        $fields = self::extractFlatFields($form);
        $subs = ContactFormTable::getSubmissions($formId, 1, 9999);

        $filename = ContactFormCsvExporter::filename($formId, date('Y-m-d'));

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Excel compatibility
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, ContactFormCsvExporter::headers($fields));

        // Data rows
        foreach ($subs as $sub) {
            fputcsv($out, ContactFormCsvExporter::row(
                $sub,
                $fields,
                static fn(int|false $timestamp): string => date_i18n('d/m/Y H:i', $timestamp ?: 0)
            ));
        }

        fclose($out);
        exit;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function buildRedirectUrl(int $formId, string $msg): string
    {
        $base = admin_url('admin.php?page=' . self::MENU_SLUG);
        if ($formId > 0) {
            return $base . '&action=edit&id=' . $formId . '&laca_msg=' . $msg;
        }
        return $base . '&laca_msg=' . $msg;
    }

    private function getFlashMessage(): ?array
    {
        $msg = sanitize_key($_GET['laca_msg'] ?? '');
        $map = [
            'saved' => ['type' => 'success', 'text' => 'Đã lưu form thành công.'],
            'deleted' => ['type' => 'success', 'text' => 'Đã xoá thành công.'],
            'marked_read' => ['type' => 'success', 'text' => 'Đã đánh dấu đã đọc.'],
            'error_name' => ['type' => 'error', 'text' => 'Vui lòng nhập tên form.'],
        ];
        return $map[$msg] ?? null;
    }
}
