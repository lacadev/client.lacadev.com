<?php

namespace App\Settings\LacaTools\Management;

use App\Databases\ContactFormTable;

/**
 * Practical overview widgets for the dedicated Laca Dashboard page.
 */
class WebsiteOverviewWidgets
{
    private const VIEW_COUNT_META = '_gm_view_count';
    private const STALE_DAYS = 180;
    private const CONTACT_FORM_MENU_SLUG = 'laca-contact-forms';

    public function __construct(
        private ContentAuditService $auditService,
        private MediaService $mediaService,
    ) {}

    public function renderTodayActionsWidget(): void
    {
        $items = $this->todayActionItems();
        $activeItems = array_filter($items, static fn ($item) => (int) $item['count'] > 0);
        ?>
        <div class="laca-overview-summary">
            <div>
                <strong><?php echo esc_html((string) count($activeItems)); ?></strong>
                <span><?php echo esc_html__('nhóm việc cần xử lý', 'laca'); ?></span>
            </div>
            <p><?php echo esc_html__('Ưu tiên các mục có số lượng lớn hoặc ảnh hưởng trực tiếp tới khách hàng.', 'laca'); ?></p>
        </div>
        <ul class="laca-overview-action-list">
            <?php foreach ($items as $item) : ?>
                <li class="<?php echo (int) $item['count'] > 0 ? 'has-work' : 'is-clear'; ?>">
                    <span class="laca-overview-action-list__label">
                        <span class="dashicons <?php echo esc_attr($item['icon']); ?>"></span>
                        <?php echo esc_html($item['label']); ?>
                    </span>
                    <span class="laca-overview-action-list__meta">
                        <strong><?php echo esc_html((string) (int) $item['count']); ?></strong>
                        <?php if (!empty($item['url']) && (int) $item['count'] > 0) : ?>
                            <a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['action']); ?></a>
                        <?php else : ?>
                            <span><?php echo esc_html__('Ổn', 'laca'); ?></span>
                        <?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    public function renderSeoStatusWidget(): void
    {
        $postTypes = $this->dashboardPostTypes();
        $total = $this->countPublishedPosts($postTypes);
        $missingDescriptions = $this->countMissingMetaDescriptions($postTypes);
        $missingImages = $this->countMissingFeaturedImages($postTypes);
        $score = $total > 0 ? max(0, (int) round(100 - (($missingDescriptions + $missingImages) / max(1, $total * 2) * 100))) : 100;
        $lowSeo = $this->lowSeoScoreSummary($postTypes);
        $issues = $this->missingMetaDescriptionPosts($postTypes, 5);
        ?>
        <div class="laca-overview-score">
            <div class="laca-overview-score__number"><?php echo esc_html((string) $score); ?><span>/100</span></div>
            <div>
                <strong><?php echo esc_html__('Tình trạng SEO nội dung', 'laca'); ?></strong>
                <p><?php echo esc_html__('Tính theo meta description, ảnh đại diện và điểm SEO nếu plugin SEO có dữ liệu.', 'laca'); ?></p>
            </div>
        </div>
        <ul class="laca-health-list">
            <li><span class="health-label"><?php echo esc_html__('Thiếu meta description', 'laca'); ?></span><span class="health-value <?php echo $missingDescriptions > 0 ? 'health-warn' : 'health-ok'; ?>"><?php echo esc_html((string) $missingDescriptions); ?></span></li>
            <li><span class="health-label"><?php echo esc_html__('Thiếu ảnh đại diện', 'laca'); ?></span><span class="health-value <?php echo $missingImages > 0 ? 'health-warn' : 'health-ok'; ?>"><?php echo esc_html((string) $missingImages); ?></span></li>
            <li><span class="health-label"><?php echo esc_html__('Điểm SEO thấp', 'laca'); ?></span><span class="health-value <?php echo $lowSeo['count'] > 0 ? 'health-warn' : 'health-ok'; ?>"><?php echo esc_html($lowSeo['label']); ?></span></li>
        </ul>
        <?php $this->renderPostList($issues, __('Nội dung cần tối ưu trước', 'laca')); ?>
        <?php
    }

    public function renderRecentLeadsWidget(): void
    {
        $hasTable = $this->contactSubmissionsTableExists();
        $unread = $hasTable ? ContactFormTable::countAllUnread() : 0;
        $submissions = $hasTable ? $this->latestContactSubmissions(5) : [];
        ?>
        <div class="laca-overview-summary">
            <div>
                <strong><?php echo esc_html((string) $unread); ?></strong>
                <span><?php echo esc_html__('lead chưa đọc', 'laca'); ?></span>
            </div>
            <p><?php echo esc_html__('Theo dõi form mới để phản hồi khách hàng nhanh hơn.', 'laca'); ?></p>
        </div>

        <?php if (!$hasTable) : ?>
            <p class="laca-overview-empty"><?php echo esc_html__('Chưa tìm thấy bảng dữ liệu form liên hệ.', 'laca'); ?></p>
        <?php elseif (empty($submissions)) : ?>
            <p class="laca-overview-empty"><?php echo esc_html__('Chưa có lead mới từ form.', 'laca'); ?></p>
        <?php else : ?>
            <ul class="laca-overview-leads">
                <?php foreach ($submissions as $lead) : ?>
                    <li>
                        <div>
                            <a href="<?php echo esc_url($lead['url']); ?>"><?php echo esc_html($lead['title']); ?></a>
                            <span><?php echo esc_html($lead['meta']); ?></span>
                        </div>
                        <?php if (!$lead['is_read']) : ?><span class="laca-overview-pill"><?php echo esc_html__('Mới', 'laca'); ?></span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <p class="laca-overview-footer">
            <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=' . self::CONTACT_FORM_MENU_SLUG)); ?>"><?php echo esc_html__('Quản lý form', 'laca'); ?></a>
        </p>
        <?php
    }

    public function renderStaleContentWidget(): void
    {
        $postTypes = $this->dashboardPostTypes();
        $count = $this->countStaleContent($postTypes);
        $posts = $this->staleContentPosts($postTypes, 5);
        ?>
        <div class="laca-overview-summary">
            <div>
                <strong><?php echo esc_html((string) $count); ?></strong>
                <span><?php echo esc_html(sprintf(__('nội dung quá %d ngày chưa cập nhật', 'laca'), self::STALE_DAYS)); ?></span>
            </div>
            <p><?php echo esc_html__('Ưu tiên bài cũ vẫn có lượt xem để giữ chất lượng SEO và chuyển đổi.', 'laca'); ?></p>
        </div>
        <?php $this->renderPostList($posts, __('Cần xem lại trước', 'laca')); ?>
        <?php
    }

    public function renderMaintenanceOverviewWidget(): void
    {
        $updates = $this->updateCount();
        $nextAudit = wp_next_scheduled('lacadev_weekly_deep_audit');
        $cronDisabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $isSsl = is_ssl() || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $maintenanceOn = get_option('_is_maintenance') === 'yes';
        ?>
        <ul class="laca-health-list">
            <li><span class="health-label"><?php echo esc_html__('WordPress', 'laca'); ?></span><span class="health-value"><?php echo esc_html(get_bloginfo('version')); ?></span></li>
            <li><span class="health-label"><?php echo esc_html__('PHP', 'laca'); ?></span><span class="health-value"><?php echo esc_html(PHP_VERSION); ?></span></li>
            <li><span class="health-label"><?php echo esc_html__('HTTPS', 'laca'); ?></span><span class="health-value <?php echo $isSsl ? 'health-ok' : 'health-warn'; ?>"><?php echo esc_html($isSsl ? __('Bật', 'laca') : __('Tắt', 'laca')); ?></span></li>
            <li><span class="health-label"><?php echo esc_html__('Cập nhật hệ thống', 'laca'); ?></span><span class="health-value <?php echo $updates > 0 ? 'health-warn' : 'health-ok'; ?>"><?php echo esc_html((string) $updates); ?><?php if ($updates > 0) : ?><a class="health-link" href="<?php echo esc_url(admin_url('update-core.php')); ?>"><?php echo esc_html__('Xem', 'laca'); ?></a><?php endif; ?></span></li>
            <li><span class="health-label"><?php echo esc_html__('WP-Cron', 'laca'); ?></span><span class="health-value <?php echo $cronDisabled ? 'health-warn' : 'health-ok'; ?>"><?php echo esc_html($cronDisabled ? __('Đang tắt', 'laca') : __('Đang bật', 'laca')); ?></span></li>
            <li><span class="health-label"><?php echo esc_html__('Audit nội dung kế tiếp', 'laca'); ?></span><span class="health-value <?php echo $nextAudit ? 'health-ok' : 'health-warn'; ?>"><?php echo esc_html($nextAudit ? date_i18n('d/m/Y H:i', $nextAudit) : __('Chưa lên lịch', 'laca')); ?></span></li>
            <li><span class="health-label"><?php echo esc_html__('Maintenance mode', 'laca'); ?></span><span class="health-value <?php echo $maintenanceOn ? 'health-warn' : 'health-ok'; ?>"><?php echo esc_html($maintenanceOn ? __('Đang bật', 'laca') : __('Đang tắt', 'laca')); ?></span></li>
        </ul>
        <?php
    }

    public function renderRoleShortcutsWidget(): void
    {
        $shortcuts = $this->roleShortcuts();
        ?>
        <div class="laca-overview-shortcuts">
            <?php foreach ($shortcuts as $shortcut) : ?>
                <a href="<?php echo esc_url($shortcut['url']); ?>">
                    <span class="dashicons <?php echo esc_attr($shortcut['icon']); ?>"></span>
                    <span><?php echo esc_html($shortcut['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function todayActionItems(): array
    {
        $postTypes = $this->dashboardPostTypes();
        $drafts = 0;
        $trash = 0;
        foreach ($postTypes as $postType) {
            $counts = wp_count_posts($postType);
            $drafts += (int) ($counts->draft ?? 0);
            $trash += (int) ($counts->trash ?? 0);
        }

        $scheduled = new \WP_Query([
            'post_type' => $postTypes,
            'post_status' => 'future',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => false,
        ]);

        $healthReport = get_option('lacadev_deep_health_report');
        $healthCount = is_array($healthReport) ? count((array) ($healthReport['issues'] ?? [])) : 0;
        $mediaStats = $this->mediaService->getMediaStats();
        $comments = (int) wp_count_comments()->moderated;
        $unreadLeads = $this->contactSubmissionsTableExists() ? ContactFormTable::countAllUnread() : 0;

        return [
            ['label' => __('Lead form chưa đọc', 'laca'), 'count' => $unreadLeads, 'url' => admin_url('admin.php?page=' . self::CONTACT_FORM_MENU_SLUG), 'action' => __('Xem', 'laca'), 'icon' => 'dashicons-email-alt2'],
            ['label' => __('Bình luận chờ duyệt', 'laca'), 'count' => $comments, 'url' => admin_url('edit-comments.php?comment_status=moderated'), 'action' => __('Duyệt', 'laca'), 'icon' => 'dashicons-admin-comments'],
            ['label' => __('Bản nháp', 'laca'), 'count' => $drafts, 'url' => admin_url('edit.php?post_status=draft'), 'action' => __('Xem', 'laca'), 'icon' => 'dashicons-edit-page'],
            ['label' => __('Đã lên lịch', 'laca'), 'count' => (int) $scheduled->found_posts, 'url' => admin_url('edit.php?post_status=future'), 'action' => __('Xem', 'laca'), 'icon' => 'dashicons-calendar-alt'],
            ['label' => __('Media không dùng', 'laca'), 'count' => (int) ($mediaStats['orphan_count'] ?? 0), 'url' => admin_url('upload.php?detached=1&mode=list'), 'action' => __('Dọn', 'laca'), 'icon' => 'dashicons-format-image'],
            ['label' => __('Nội dung có lỗi audit', 'laca'), 'count' => $healthCount, 'url' => admin_url('admin.php?page=' . LacaDashboardPage::SLUG), 'action' => __('Xem', 'laca'), 'icon' => 'dashicons-warning'],
            ['label' => __('Thùng rác', 'laca'), 'count' => $trash, 'url' => admin_url('edit.php?post_status=trash'), 'action' => __('Dọn', 'laca'), 'icon' => 'dashicons-trash'],
            ['label' => __('Cập nhật hệ thống', 'laca'), 'count' => $this->updateCount(), 'url' => admin_url('update-core.php'), 'action' => __('Cập nhật', 'laca'), 'icon' => 'dashicons-update'],
        ];
    }

    private function dashboardPostTypes(): array
    {
        $postTypes = $this->auditService->getDashboardPostTypes();

        return !empty($postTypes) ? $postTypes : ['post', 'page'];
    }

    private function countPublishedPosts(array $postTypes): int
    {
        $total = 0;
        foreach ($postTypes as $postType) {
            $counts = wp_count_posts($postType);
            $total += (int) ($counts->publish ?? 0);
        }

        return $total;
    }

    private function countMissingMetaDescriptions(array $postTypes): int
    {
        global $wpdb;
        if (empty($postTypes)) {
            return 0;
        }

        $metaKeys = ['_yoast_wpseo_metadesc', 'rank_math_description', '_seopress_titles_desc'];
        $metaPlaceholders = implode(',', array_fill(0, count($metaKeys), '%s'));
        $typePlaceholders = implode(',', array_fill(0, count($postTypes), '%s'));

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm
                ON p.ID = pm.post_id
                AND pm.meta_key IN ($metaPlaceholders)
                AND TRIM(pm.meta_value) <> ''
             WHERE p.post_type IN ($typePlaceholders)
                AND p.post_status = 'publish'
                AND pm.post_id IS NULL",
            array_merge($metaKeys, $postTypes)
        ));
    }

    private function missingMetaDescriptionPosts(array $postTypes, int $limit): array
    {
        global $wpdb;
        if (empty($postTypes)) {
            return [];
        }

        $metaKeys = ['_yoast_wpseo_metadesc', 'rank_math_description', '_seopress_titles_desc'];
        $metaPlaceholders = implode(',', array_fill(0, count($metaKeys), '%s'));
        $typePlaceholders = implode(',', array_fill(0, count($postTypes), '%s'));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_type, p.post_modified
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm
                ON p.ID = pm.post_id
                AND pm.meta_key IN ($metaPlaceholders)
                AND TRIM(pm.meta_value) <> ''
             WHERE p.post_type IN ($typePlaceholders)
                AND p.post_status = 'publish'
                AND pm.post_id IS NULL
             ORDER BY p.post_modified DESC
             LIMIT %d",
            array_merge($metaKeys, $postTypes, [$limit])
        ));

        return $this->mapPostRows((array) $rows, __('Thiếu meta description', 'laca'));
    }

    private function countMissingFeaturedImages(array $postTypes): int
    {
        global $wpdb;
        $postTypes = array_values(array_diff($postTypes, ['page']));
        if (empty($postTypes)) {
            return 0;
        }

        $typePlaceholders = implode(',', array_fill(0, count($postTypes), '%s'));

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm
                ON p.ID = pm.post_id
                AND pm.meta_key = '_thumbnail_id'
                AND pm.meta_value <> ''
                AND pm.meta_value <> '0'
             WHERE p.post_type IN ($typePlaceholders)
                AND p.post_status = 'publish'
                AND pm.post_id IS NULL",
            $postTypes
        ));
    }

    private function lowSeoScoreSummary(array $postTypes): array
    {
        global $wpdb;
        $metaKey = $this->seoScoreMetaKey();
        if (!$metaKey || empty($postTypes)) {
            return ['count' => 0, 'label' => __('Không có dữ liệu', 'laca')];
        }

        $threshold = $metaKey === '_yoast_wpseo_linkdex' ? 60 : 70;
        $typePlaceholders = implode(',', array_fill(0, count($postTypes), '%s'));
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type IN ($typePlaceholders)
                AND p.post_status = 'publish'
                AND pm.meta_key = %s
                AND CAST(pm.meta_value AS UNSIGNED) > 0
                AND CAST(pm.meta_value AS UNSIGNED) < %d",
            array_merge($postTypes, [$metaKey, $threshold])
        ));

        return ['count' => $count, 'label' => (string) $count];
    }

    private function countStaleContent(array $postTypes): int
    {
        global $wpdb;
        if (empty($postTypes)) {
            return 0;
        }

        $typePlaceholders = implode(',', array_fill(0, count($postTypes), '%s'));

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->posts}
             WHERE post_type IN ($typePlaceholders)
                AND post_status = 'publish'
                AND post_modified < %s",
            array_merge($postTypes, [$this->staleDate()])
        ));
    }

    private function staleContentPosts(array $postTypes, int $limit): array
    {
        global $wpdb;
        if (empty($postTypes)) {
            return [];
        }

        $typePlaceholders = implode(',', array_fill(0, count($postTypes), '%s'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_type, p.post_modified, COALESCE(pm.meta_value + 0, 0) AS views
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm
                ON p.ID = pm.post_id AND pm.meta_key = %s
             WHERE p.post_type IN ($typePlaceholders)
                AND p.post_status = 'publish'
                AND p.post_modified < %s
             ORDER BY views DESC, p.post_modified ASC
             LIMIT %d",
            array_merge([self::VIEW_COUNT_META], $postTypes, [$this->staleDate(), $limit])
        ));

        return $this->mapPostRows((array) $rows, __('Cần cập nhật', 'laca'), true);
    }

    private function latestContactSubmissions(int $limit): array
    {
        global $wpdb;
        $subTable = ContactFormTable::getSubmissionsTable();
        $formsTable = ContactFormTable::getFormsTable();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, f.name AS form_name
             FROM {$subTable} s
             LEFT JOIN {$formsTable} f ON f.id = s.form_id
             ORDER BY s.created_at DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);

        $items = [];
        foreach ((array) $rows as $row) {
            $data = json_decode((string) ($row['data'] ?? ''), true);
            $data = is_array($data) ? $data : [];
            $name = $this->firstMatchingField($data, ['name', 'ten', 'full_name', 'fullname']) ?: __('Khách liên hệ', 'laca');
            $phone = $this->firstMatchingField($data, ['phone', 'tel', 'dien_thoai', 'sdt']);
            $email = $this->firstMatchingField($data, ['email', 'mail']);
            $contact = $phone ?: $email ?: __('Chưa có thông tin liên hệ', 'laca');
            $createdAt = !empty($row['created_at']) ? mysql2date('d/m/Y H:i', $row['created_at']) : '';

            $items[] = [
                'title' => $name,
                'meta' => trim(($row['form_name'] ?: __('Form liên hệ', 'laca')) . ' · ' . $contact . ' · ' . $createdAt, ' ·'),
                'url' => admin_url('admin.php?page=' . self::CONTACT_FORM_MENU_SLUG . '&action=submissions&id=' . (int) $row['form_id']),
                'is_read' => (int) ($row['is_read'] ?? 0) === 1,
            ];
        }

        return $items;
    }

    private function roleShortcuts(): array
    {
        $shortcuts = [
            ['label' => __('Xem website', 'laca'), 'url' => home_url('/'), 'icon' => 'dashicons-external'],
        ];

        if (current_user_can('edit_posts')) {
            $shortcuts[] = ['label' => __('Viết bài mới', 'laca'), 'url' => admin_url('post-new.php'), 'icon' => 'dashicons-edit'];
            $shortcuts[] = ['label' => __('Bản nháp', 'laca'), 'url' => admin_url('edit.php?post_status=draft'), 'icon' => 'dashicons-clipboard'];
        }

        if (current_user_can('edit_pages')) {
            $shortcuts[] = ['label' => __('Quản lý trang', 'laca'), 'url' => admin_url('edit.php?post_type=page'), 'icon' => 'dashicons-admin-page'];
        }

        if (current_user_can('upload_files')) {
            $shortcuts[] = ['label' => __('Thư viện media', 'laca'), 'url' => admin_url('upload.php'), 'icon' => 'dashicons-format-image'];
        }

        if (current_user_can('manage_options')) {
            $shortcuts[] = ['label' => __('Cấu hình theme', 'laca'), 'url' => admin_url('admin.php?page=app-theme-options.php'), 'icon' => 'dashicons-admin-generic'];
            $shortcuts[] = ['label' => __('Form liên hệ', 'laca'), 'url' => admin_url('admin.php?page=' . self::CONTACT_FORM_MENU_SLUG), 'icon' => 'dashicons-feedback'];
            $shortcuts[] = ['label' => __('Cập nhật', 'laca'), 'url' => admin_url('update-core.php'), 'icon' => 'dashicons-update'];
        }

        return $shortcuts;
    }

    private function renderPostList(array $posts, string $title): void
    {
        if (empty($posts)) {
            echo '<p class="laca-overview-empty">' . esc_html__('Không có mục cần xử lý.', 'laca') . '</p>';
            return;
        }
        ?>
        <div class="hub-section-title"><?php echo esc_html($title); ?></div>
        <ul class="laca-post-list laca-overview-posts">
            <?php foreach ($posts as $post) : ?>
                <li>
                    <div class="laca-post-info">
                        <a class="laca-post-link" href="<?php echo esc_url($post['url']); ?>"><?php echo esc_html($post['title']); ?></a>
                        <span class="laca-post-meta"><?php echo esc_html($post['meta']); ?></span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    private function mapPostRows(array $rows, string $reason, bool $includeViews = false): array
    {
        $items = [];
        foreach ((array) $rows as $row) {
            $postType = get_post_type_object($row->post_type);
            $meta = trim(($postType ? $postType->labels->singular_name : $row->post_type) . ' · ' . $reason . ' · ' . mysql2date('d/m/Y', $row->post_modified), ' ·');
            if ($includeViews) {
                $meta .= ' · ' . sprintf(__('%d lượt xem', 'laca'), (int) ($row->views ?? 0));
            }

            $items[] = [
                'title' => $this->decodeTitle((string) $row->post_title),
                'url' => get_edit_post_link((int) $row->ID, 'raw') ?: admin_url('post.php?post=' . (int) $row->ID . '&action=edit'),
                'meta' => $meta,
            ];
        }

        return $items;
    }

    private function firstMatchingField(array $data, array $needles): string
    {
        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            foreach ($needles as $needle) {
                if (str_contains($normalizedKey, $needle) && is_scalar($value) && trim((string) $value) !== '') {
                    return trim((string) $value);
                }
            }
        }

        foreach ($data as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return '';
    }

    private function contactSubmissionsTableExists(): bool
    {
        global $wpdb;
        if (!class_exists(ContactFormTable::class)) {
            return false;
        }

        $table = ContactFormTable::getSubmissionsTable();

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
    }

    private function updateCount(): int
    {
        if (!function_exists('wp_get_update_data') && file_exists(ABSPATH . 'wp-admin/includes/update.php')) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }

        if (!function_exists('wp_get_update_data')) {
            return 0;
        }

        $data = wp_get_update_data();

        return (int) ($data['counts']['total'] ?? 0);
    }

    private function seoScoreMetaKey(): string
    {
        if (defined('RANK_MATH_VERSION')) {
            return 'rank_math_seo_score';
        }

        if (defined('WPSEO_VERSION')) {
            return '_yoast_wpseo_linkdex';
        }

        return '';
    }

    private function staleDate(): string
    {
        return date('Y-m-d H:i:s', strtotime('-' . self::STALE_DAYS . ' days', current_time('timestamp')));
    }

    private function decodeTitle(string $title): string
    {
        return htmlspecialchars_decode(html_entity_decode($title, ENT_QUOTES, 'UTF-8'), ENT_QUOTES);
    }
}
