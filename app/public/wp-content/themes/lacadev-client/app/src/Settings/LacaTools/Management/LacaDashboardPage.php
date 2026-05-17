<?php

namespace App\Settings\LacaTools\Management;

use App\Settings\Admin\AdminDashboardIntroWidget;
use App\Widgets\BlockSyncWidget;

/**
 * Renders the former dashboard widgets as a dedicated admin page.
 */
class LacaDashboardPage
{
    public const SLUG = 'lacadev-dashboard';
    private const ORDER_META_KEY = 'laca_dashboard_widget_order';
    private const SPAN_META_KEY = 'laca_dashboard_widget_spans';

    public function __construct(
        private DashboardWidgets $dashboardWidgets,
        private WebsiteOverviewWidgets $overviewWidgets,
        private QuickNotesWidget $quickNotesWidget,
        private PerformanceBudgetWidget $performanceBudgetWidget,
        private BlockSyncWidget $blockSyncWidget,
    ) {}

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_ajax_laca_dashboard_save_layout', [$this, 'saveLayout']);
    }

    public function addMenuPage(): void
    {
        add_menu_page(
            __('Laca Dashboard', 'laca'),
            __('Laca Dashboard', 'laca'),
            'manage_options',
            self::SLUG,
            [$this, 'render'],
            'dashicons-dashboard',
            '2.5'
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== $this->hookSuffix()) {
            return;
        }

        wp_register_style('laca-dashboard-page', false, [], wp_get_theme()->get('Version') ?: '1.0.0');
        wp_enqueue_style('laca-dashboard-page');
        wp_add_inline_style('laca-dashboard-page', $this->inlineCss());

        wp_enqueue_script('jquery');
        wp_localize_script('jquery', 'lacaDashboardLayout', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('laca_dashboard_layout'),
            'i18n' => [
                'saving' => __('Đang lưu...', 'laca'),
                'saved' => __('Đã lưu thứ tự', 'laca'),
                'error' => __('Không lưu được thứ tự', 'laca'),
                'resetConfirm' => __('Đưa Laca Dashboard về thứ tự mặc định?', 'laca'),
            ],
        ]);
        wp_add_inline_script('jquery', $this->inlineJs(), 'after');
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Bạn không có quyền truy cập trang này.', 'laca'));
        }
        ?>
        <div class="wrap laca-dashboard-page">
            <div class="laca-dashboard-page__header">
                <div>
                    <h1><?php echo esc_html__('Laca Dashboard', 'laca'); ?></h1>
                    <p><?php echo esc_html__('Kéo tiêu đề từng widget để tự sắp xếp dashboard theo cách làm việc của bạn.', 'laca'); ?></p>
                </div>
                <div class="laca-dashboard-page__actions">
                    <span class="laca-dashboard-layout-status" aria-live="polite"></span>
                    <button type="button" class="button laca-dashboard-reset-layout">
                        <span class="dashicons dashicons-image-rotate"></span>
                        <?php echo esc_html__('Reset', 'laca'); ?>
                    </button>
                </div>
            </div>

            <div class="laca-dashboard-grid" id="laca-dashboard-grid">
                <?php foreach ($this->orderedWidgets() as $widget) : ?>
                    <?php $this->renderCard($widget['id'], $widget['title'], $widget['callback'], $widget['span'], $widget['flags']); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    public function saveLayout(): void
    {
        if (!check_ajax_referer('laca_dashboard_layout', 'nonce', false)) {
            wp_send_json_error(['message' => __('Nonce không hợp lệ.', 'laca')], 403);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Bạn không có quyền lưu layout.', 'laca')], 403);
        }

        if (!empty($_POST['reset'])) {
            delete_user_meta(get_current_user_id(), self::ORDER_META_KEY);
            delete_user_meta(get_current_user_id(), self::SPAN_META_KEY);
            wp_send_json_success(['reset' => true]);
        }

        $validIds = array_keys($this->widgetDefinitions());
        $rawOrder = isset($_POST['order']) ? wp_unslash($_POST['order']) : [];
        $order = array_map('sanitize_key', (array) $rawOrder);
        $order = array_values(array_intersect($order, $validIds));
        $rawSpans = isset($_POST['spans']) ? wp_unslash($_POST['spans']) : [];
        $spans = [];

        if (is_array($rawSpans)) {
            foreach ($rawSpans as $id => $span) {
                $id = sanitize_key((string) $id);
                $span = (int) $span;
                if (in_array($id, $validIds, true) && in_array($span, [4, 6, 8, 12], true)) {
                    $spans[$id] = $span;
                }
            }
        }

        update_user_meta(get_current_user_id(), self::ORDER_META_KEY, $order);
        update_user_meta(get_current_user_id(), self::SPAN_META_KEY, $spans);
        wp_send_json_success(['order' => $order, 'spans' => $spans]);
    }

    public function renderIntroCard(): void
    {
        echo AdminDashboardIntroWidget::html(
            defined('AUTHOR') ? AUTHOR : [],
            get_site_url() . '/wp-content/themes/lacadev/resources/images/dev/moomsdev-black.png'
        );
    }

    private function renderCard(string $id, string $title, callable $callback, int $span, array $flags = []): void
    {
        $class = 'laca-dashboard-card laca-dashboard-card--span-' . $span;
        foreach ($flags as $flag) {
            $class .= ' laca-dashboard-card--' . sanitize_html_class((string) $flag);
        }
        ?>
        <section class="<?php echo esc_attr($class); ?>" data-widget="<?php echo esc_attr($id); ?>" data-span="<?php echo esc_attr((string) $span); ?>">
            <header class="laca-dashboard-card__header">
                <h2><?php echo esc_html($title); ?></h2>
                <div class="laca-dashboard-card__tools">
                    <div class="laca-dashboard-card__sizes" aria-label="<?php echo esc_attr(sprintf(__('Chọn số cột cho %s', 'laca'), $title)); ?>">
                        <?php foreach ($this->spanOptions() as $value => $label) : ?>
                            <button type="button" class="laca-dashboard-card__size<?php echo $span === $value ? ' is-active' : ''; ?>" data-span="<?php echo esc_attr((string) $value); ?>">
                                <?php echo esc_html($label); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="laca-dashboard-card__drag" aria-label="<?php echo esc_attr(sprintf(__('Kéo để sắp xếp %s', 'laca'), $title)); ?>">
                        <span class="dashicons dashicons-move"></span>
                    </button>
                </div>
            </header>
            <div class="laca-dashboard-card__body inside">
                <?php call_user_func($callback); ?>
            </div>
        </section>
        <?php
    }

    private function orderedWidgets(): array
    {
        $widgets = $this->widgetDefinitions();
        $savedOrder = get_user_meta(get_current_user_id(), self::ORDER_META_KEY, true);
        $savedOrder = is_array($savedOrder) ? array_map('sanitize_key', $savedOrder) : [];
        $savedSpans = get_user_meta(get_current_user_id(), self::SPAN_META_KEY, true);
        $savedSpans = is_array($savedSpans) ? $savedSpans : [];

        foreach ($widgets as $id => $widget) {
            $span = isset($savedSpans[$id]) ? (int) $savedSpans[$id] : (int) $widget['span'];
            if (in_array($span, [4, 6, 8, 12], true)) {
                $widgets[$id]['span'] = $span;
            }
        }

        $ordered = [];
        foreach ($savedOrder as $id) {
            if (isset($widgets[$id])) {
                $ordered[$id] = $widgets[$id];
            }
        }

        foreach ($widgets as $id => $widget) {
            if (!isset($ordered[$id])) {
                $ordered[$id] = $widget;
            }
        }

        return array_values($ordered);
    }

    private function widgetDefinitions(): array
    {
        $widgets = [
            'today_actions' => [
                'id' => 'today_actions',
                'title' => __('Việc cần xử lý hôm nay', 'laca'),
                'callback' => [$this->overviewWidgets, 'renderTodayActionsWidget'],
                'span' => 12,
                'flags' => ['priority'],
            ],
            'business_hub' => [
                'id' => 'business_hub',
                'title' => __('LacaDev Business Hub', 'laca'),
                'callback' => [$this->dashboardWidgets, 'renderDashboardWidget'],
                'span' => 12,
                'flags' => ['hero'],
            ],
            'content_report' => [
                'id' => 'content_report',
                'title' => __('Báo cáo Nội dung', 'laca'),
                'callback' => [$this->dashboardWidgets, 'renderContentTrackerWidget'],
                'span' => 12,
                'flags' => [],
            ],
            'recent_leads' => [
                'id' => 'recent_leads',
                'title' => __('Form & Lead gần đây', 'laca'),
                'callback' => [$this->overviewWidgets, 'renderRecentLeadsWidget'],
                'span' => 6,
                'flags' => [],
            ],
            'seo_status' => [
                'id' => 'seo_status',
                'title' => __('Tình trạng SEO nội dung', 'laca'),
                'callback' => [$this->overviewWidgets, 'renderSeoStatusWidget'],
                'span' => 6,
                'flags' => [],
            ],
            'stale_content' => [
                'id' => 'stale_content',
                'title' => __('Nội dung cần cập nhật', 'laca'),
                'callback' => [$this->overviewWidgets, 'renderStaleContentWidget'],
                'span' => 6,
                'flags' => [],
            ],
            'maintenance_overview' => [
                'id' => 'maintenance_overview',
                'title' => __('Bảo trì website', 'laca'),
                'callback' => [$this->overviewWidgets, 'renderMaintenanceOverviewWidget'],
                'span' => 6,
                'flags' => [],
            ],
            'media' => [
                'id' => 'media',
                'title' => __('Thư viện Media', 'laca'),
                'callback' => [$this->dashboardWidgets, 'renderMediaLibraryWidget'],
                'span' => 4,
                'flags' => [],
            ],
            'site_health' => [
                'id' => 'site_health',
                'title' => __('Tình trạng Website', 'laca'),
                'callback' => [$this->dashboardWidgets, 'renderSiteHealthWidget'],
                'span' => 4,
                'flags' => [],
            ],
            'todo' => [
                'id' => 'todo',
                'title' => __('Việc cần làm', 'laca'),
                'callback' => [$this->dashboardWidgets, 'renderTodoWidget'],
                'span' => 4,
                'flags' => [],
            ],
            'role_shortcuts' => [
                'id' => 'role_shortcuts',
                'title' => __('Truy cập nhanh theo vai trò', 'laca'),
                'callback' => [$this->overviewWidgets, 'renderRoleShortcutsWidget'],
                'span' => 4,
                'flags' => [],
            ],
            'quick_search' => [
                'id' => 'quick_search',
                'title' => __('Tìm kiếm nhanh', 'laca'),
                'callback' => [$this->dashboardWidgets, 'renderQuickSearchWidget'],
                'span' => 4,
                'flags' => [],
            ],
            'quick_notes' => [
                'id' => 'quick_notes',
                'title' => __('Ghi chú nhanh', 'laca'),
                'callback' => [$this->quickNotesWidget, 'renderWidget'],
                'span' => 4,
                'flags' => [],
            ],
        ];

        if (post_type_exists('project')) {
            $widgets['project_charts'] = [
                'id' => 'project_charts',
                'title' => __('Thống kê Dự án', 'laca'),
                'callback' => [$this->dashboardWidgets, 'renderProjectChartsWidget'],
                'span' => 6,
                'flags' => [],
            ];
        }

        if (!$this->isDevUser()) {
            return $widgets;
        }

        $widgets += [
            'performance' => [
                'id' => 'performance',
                'title' => __('Performance Budget', 'laca'),
                'callback' => [$this->performanceBudgetWidget, 'renderWidget'],
                'span' => 6,
                'flags' => [],
            ],
            'block_updates' => [
                'id' => 'block_updates',
                'title' => __('LacaDev Block Updates', 'laca'),
                'callback' => [$this->blockSyncWidget, 'render'],
                'span' => 6,
                'flags' => [],
            ],
            'client_ops' => [
                'id' => 'client_ops',
                'title' => __('LacaDev Client Operations', 'laca'),
                'callback' => [$this->dashboardWidgets, 'renderClientOperationsWidget'],
                'span' => 6,
                'flags' => [],
            ],
        ];

        return $widgets;
    }

    private function isDevUser(): bool
    {
        $user = wp_get_current_user();

        return $user && $user->exists() && $user->user_login === 'lacadev';
    }

    private function spanOptions(): array
    {
        return [
            4 => '1/3',
            6 => '1/2',
            8 => '2/3',
            12 => 'Full',
        ];
    }

    private function hookSuffix(): string
    {
        return 'toplevel_page_' . self::SLUG;
    }

    private function inlineJs(): string
    {
        return '
(function() {
    "use strict";

    function boot() {
    const config = window.lacaDashboardLayout || {};
    const grid = document.getElementById("laca-dashboard-grid");
    const status = document.querySelector(".laca-dashboard-layout-status");
    let saveTimer = null;
    let active = null;
    let originalOrder = [];

    if (!grid) {
        return;
    }

    function setStatus(text, state) {
        if (!status) {
            return;
        }

        status.classList.remove("is-saving", "is-saved", "is-error");
        if (state) {
            status.classList.add("is-" + state);
        }
        status.textContent = text || "";
    }

    function collectOrder() {
        return Array.from(grid.querySelectorAll(".laca-dashboard-card"))
            .map(function(card) {
                return String(card.dataset.widget || "");
            })
            .filter(Boolean);
    }

    function collectSpans() {
        const spans = {};
        grid.querySelectorAll(".laca-dashboard-card").forEach(function(card) {
            const id = String(card.dataset.widget || "");
            const span = parseInt(card.dataset.span || "4", 10);
            if (id && [4, 6, 8, 12].indexOf(span) !== -1) {
                spans[id] = span;
            }
        });
        return spans;
    }

    function saveOrder(reset) {
        clearTimeout(saveTimer);
        setStatus(config.i18n && config.i18n.saving ? config.i18n.saving : "Saving...", "saving");

        const body = new URLSearchParams();
        body.append("action", "laca_dashboard_save_layout");
        body.append("nonce", config.nonce || "");
        body.append("reset", reset ? "1" : "");
        if (!reset) {
            collectOrder().forEach(function(id) {
                body.append("order[]", id);
            });
            const spans = collectSpans();
            Object.keys(spans).forEach(function(id) {
                body.append("spans[" + id + "]", String(spans[id]));
            });
        }

        fetch(config.ajaxUrl, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: body.toString()
        }).then(function(response) {
            return response.json();
        }).then(function(response) {
            if (!response || !response.success) {
                setStatus(config.i18n && config.i18n.error ? config.i18n.error : "Save failed", "error");
                return;
            }

            if (reset) {
                window.location.reload();
                return;
            }

            setStatus(config.i18n && config.i18n.saved ? config.i18n.saved : "Saved", "saved");
            saveTimer = setTimeout(function() {
                setStatus("", "");
            }, 1800);
        }).catch(function() {
            setStatus(config.i18n && config.i18n.error ? config.i18n.error : "Save failed", "error");
        });
    }

    function sameOrder(before, after) {
        return before.length === after.length && before.every(function(id, index) {
            return id === after[index];
        });
    }

    function resetActiveCard() {
        if (!active) {
            return;
        }

        active.card.classList.remove("is-dragging");
        active.card.style.position = "";
        active.card.style.zIndex = "";
        active.card.style.width = "";
        active.card.style.left = "";
        active.card.style.top = "";
        active.card.style.pointerEvents = "";
        active.card.style.transform = "";
        document.body.classList.remove("laca-dashboard-is-sorting");
    }

    function moveActiveCard(event) {
        if (!active) {
            return;
        }

        event.preventDefault();
        active.card.style.left = (event.clientX - active.offsetX) + "px";
        active.card.style.top = (event.clientY - active.offsetY) + "px";

        const hovered = document.elementFromPoint(event.clientX, event.clientY);
        const hoveredCard = hovered ? hovered.closest(".laca-dashboard-card") : null;
        if (hoveredCard && hoveredCard !== active.card && grid.contains(hoveredCard)) {
            const box = hoveredCard.getBoundingClientRect();
            const insertAfter = event.clientY > box.top + box.height / 2;
            grid.insertBefore(active.placeholder, insertAfter ? hoveredCard.nextSibling : hoveredCard);
            return;
        }

        const candidates = Array.from(grid.querySelectorAll(".laca-dashboard-card")).filter(function(card) {
            return card !== active.card;
        });
        const closest = candidates.reduce(function(best, card) {
            const box = card.getBoundingClientRect();
            const centerX = box.left + box.width / 2;
            const centerY = box.top + box.height / 2;
            const dx = event.clientX - centerX;
            const dy = event.clientY - centerY;
            const distance = Math.sqrt(dx * dx + dy * dy);
            return distance < best.distance ? { distance: distance, card: card, box: box } : best;
        }, { distance: Number.POSITIVE_INFINITY, card: null, box: null });

        if (!closest.card) {
            if (grid.lastElementChild !== active.placeholder) {
                grid.appendChild(active.placeholder);
            }
            return;
        }

        const insertAfter = event.clientY > closest.box.top + closest.box.height / 2;
        grid.insertBefore(active.placeholder, insertAfter ? closest.card.nextSibling : closest.card);
    }

    function finishSort() {
        if (!active) {
            return;
        }

        active.placeholder.replaceWith(active.card);
        resetActiveCard();

        const nextOrder = collectOrder();
        active = null;

        if (!sameOrder(originalOrder, nextOrder)) {
            saveOrder(false);
        }
    }

    function cancelSort() {
        if (!active) {
            return;
        }

        active.placeholder.replaceWith(active.card);
        resetActiveCard();
        active = null;
    }

    grid.addEventListener("pointerdown", function(event) {
        const header = event.target.closest(".laca-dashboard-card__header");
        const dragButton = event.target.closest(".laca-dashboard-card__drag");
        const card = event.target.closest(".laca-dashboard-card");

        if ((!header && !dragButton) || !card || !grid.contains(card) || event.button !== 0) {
            return;
        }

        if (!dragButton && event.target.closest("a, button, input, textarea, select")) {
            return;
        }

        event.preventDefault();
        originalOrder = collectOrder();

        const rect = card.getBoundingClientRect();
        const placeholder = document.createElement("div");
        placeholder.className = "laca-dashboard-card-placeholder " + Array.from(card.classList).filter(function(name) {
            return name.indexOf("laca-dashboard-card--span-") === 0;
        }).join(" ");
        placeholder.style.height = rect.height + "px";

        grid.insertBefore(placeholder, card.nextSibling);

        active = {
            card: card,
            placeholder: placeholder,
            offsetX: event.clientX - rect.left,
            offsetY: event.clientY - rect.top
        };

        card.classList.add("is-dragging");
        card.style.position = "fixed";
        card.style.zIndex = "100000";
        card.style.width = rect.width + "px";
        card.style.left = rect.left + "px";
        card.style.top = rect.top + "px";
        card.style.pointerEvents = "none";
        card.style.transform = "none";
        document.body.classList.add("laca-dashboard-is-sorting");

        document.addEventListener("pointermove", moveActiveCard);
        document.addEventListener("pointerup", function onPointerUp() {
            document.removeEventListener("pointermove", moveActiveCard);
            document.removeEventListener("pointerup", onPointerUp);
            finishSort();
        });
    });

    document.addEventListener("keydown", function(event) {
        if (event.key === "Escape") {
            cancelSort();
        }
    });

    grid.addEventListener("click", function(event) {
        const button = event.target.closest(".laca-dashboard-card__size");
        if (!button) {
            return;
        }

        const card = button.closest(".laca-dashboard-card");
        const span = parseInt(button.dataset.span || "4", 10);
        if (!card || [4, 6, 8, 12].indexOf(span) === -1) {
            return;
        }

        card.classList.remove(
            "laca-dashboard-card--span-4",
            "laca-dashboard-card--span-6",
            "laca-dashboard-card--span-8",
            "laca-dashboard-card--span-12"
        );
        card.classList.add("laca-dashboard-card--span-" + span);
        card.dataset.span = String(span);

        card.querySelectorAll(".laca-dashboard-card__size").forEach(function(item) {
            item.classList.toggle("is-active", item === button);
        });

        saveOrder(false);
    });

    const resetButton = document.querySelector(".laca-dashboard-reset-layout");
    if (resetButton) {
        resetButton.addEventListener("click", function() {
            var message = config.i18n && config.i18n.resetConfirm ? config.i18n.resetConfirm : "Reset dashboard layout?";
            if (window.confirm(message)) {
                saveOrder(true);
            }
        });
    }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
}());
';
    }

    private function inlineCss(): string
    {
        return '
.laca-dashboard-page {
    max-width: 1540px;
}
.laca-dashboard-page__header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
    margin: 22px 0 16px;
    padding-bottom: 14px;
    border-bottom: 1px solid #dcdcde;
}
.laca-dashboard-page__header h1 {
    margin: 0;
    font-size: 26px;
    line-height: 1.2;
    font-weight: 700;
    letter-spacing: 0;
}
.laca-dashboard-page__header p {
    margin: 5px 0 0;
    color: #646970;
    font-size: 13px;
}
.laca-dashboard-page__actions {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 32px;
}
.laca-dashboard-layout-status {
    color: #646970;
    font-size: 12px;
    min-width: 82px;
    text-align: right;
}
.laca-dashboard-layout-status.is-saving { color: #8a6d00; }
.laca-dashboard-layout-status.is-saved { color: #008a20; }
.laca-dashboard-layout-status.is-error { color: #b32d2e; }
.laca-dashboard-reset-layout {
    display: inline-flex !important;
    align-items: center;
    gap: 5px;
}
.laca-dashboard-reset-layout .dashicons {
    width: 16px;
    height: 16px;
    font-size: 16px;
    line-height: 1;
}
.toplevel_page_lacadev-dashboard #wpbody-content > .notice,
.toplevel_page_lacadev-dashboard #wpbody-content > .updated,
.toplevel_page_lacadev-dashboard #wpbody-content > .error {
    max-width: 760px;
    margin: 12px auto 4px;
    border-radius: 8px;
    box-shadow: none;
}
.laca-dashboard-grid {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: 14px;
    align-items: start;
}
.laca-dashboard-card {
    min-width: 0;
    background: #fff;
    border: 1px solid #dcdcde;
    border-radius: 8px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    overflow: hidden;
}
.laca-dashboard-card.is-dragging {
    opacity: .78;
    box-shadow: 0 14px 34px rgba(0, 0, 0, 0.16);
    pointer-events: none;
    transform: none;
}
.laca-dashboard-card-placeholder {
    min-height: 120px;
    border: 1px dashed #8c8f94;
    border-radius: 8px;
    background: #f6f7f7;
    grid-column: span 4;
}
.laca-dashboard-is-sorting {
    user-select: none;
    cursor: grabbing;
}
.laca-dashboard-card--span-12,
.laca-dashboard-card-placeholder.laca-dashboard-card--span-12 {
    grid-column: 1 / -1;
}
.laca-dashboard-card--span-8,
.laca-dashboard-card-placeholder.laca-dashboard-card--span-8 {
    grid-column: span 8;
}
.laca-dashboard-card--span-6,
.laca-dashboard-card-placeholder.laca-dashboard-card--span-6 {
    grid-column: span 6;
}
.laca-dashboard-card--span-4,
.laca-dashboard-card-placeholder.laca-dashboard-card--span-4 {
    grid-column: span 4;
}
.laca-dashboard-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 14px;
    border-bottom: 1px solid #dcdcde;
    background: #fbfbfc;
    cursor: grab;
    touch-action: none;
}
.laca-dashboard-card__header:active { cursor: grabbing; }
.laca-dashboard-card__header h2 {
    margin: 0;
    font-size: 13px;
    line-height: 1.4;
    font-weight: 700;
    min-width: 0;
}
.laca-dashboard-card__tools {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 0 0 auto;
}
.laca-dashboard-card__sizes {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    padding: 2px;
    border: 1px solid #dcdcde;
    border-radius: 7px;
    background: #fff;
}
.laca-dashboard-card__size {
    min-width: 34px;
    height: 24px;
    padding: 0 7px;
    border: 0;
    border-radius: 5px;
    background: transparent;
    color: #646970;
    cursor: pointer;
    font-size: 11px;
    line-height: 24px;
}
.laca-dashboard-card__size:hover,
.laca-dashboard-card__size:focus {
    background: #f0f6fc;
    color: #135e96;
    outline: none;
}
.laca-dashboard-card__size.is-active {
    background: #2271b1;
    color: #fff;
}
.laca-dashboard-card__drag {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    flex: 0 0 28px;
    margin: -5px -7px -5px 0;
    padding: 0;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: #787c82;
    cursor: grab;
}
.laca-dashboard-card__drag:hover,
.laca-dashboard-card__drag:focus {
    background: #eef0f2;
    color: #1d2327;
    outline: none;
}
.laca-dashboard-card__drag:active {
    cursor: grabbing;
}
.laca-dashboard-card__drag .dashicons {
    width: 18px;
    height: 18px;
    font-size: 18px;
    line-height: 1;
}
.laca-dashboard-card__body {
    padding: 14px;
}
.laca-dashboard-card__body > :first-child {
    margin-top: 0;
}
.laca-dashboard-card__body > :last-child {
    margin-bottom: 0;
}
.laca-dashboard-card .laca-bsw__stats {
    margin: -14px -14px 14px;
    border-radius: 0;
}
.laca-dashboard-card .laca-perf-footer {
    margin-top: 12px;
}
.laca-dashboard-card--hero .lacadev-dashboard-grid {
    grid-template-columns: repeat(auto-fit, minmax(82px, 112px));
    gap: 8px;
    margin-bottom: 12px;
}
.laca-dashboard-card--hero .lacadev-dashboard-grid .stat-item {
    min-height: 58px;
    padding: 8px;
    border-radius: 8px;
    box-shadow: none;
}
.laca-dashboard-card--hero .lacadev-dashboard-grid .stat-value {
    font-size: 17px;
}
.laca-dashboard-card--priority .laca-dashboard-card__header {
    background: #f6fbff;
}
.laca-dashboard-card--hero .lacadev-actions-list {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}
.laca-dashboard-card--hero .lacadev-btn-quick,
.laca-dashboard-card .laca-todo-item {
    min-height: 34px;
    padding: 7px 10px;
    border-radius: 8px;
    box-shadow: none;
}
.laca-dashboard-card .hub-section-title {
    margin: 12px 0 8px;
    padding-bottom: 7px;
    font-size: 12px;
}
.laca-dashboard-card .laca-health-list li {
    padding: 9px 0;
}
.laca-dashboard-card .laca-widget-header-row {
    margin: -14px -14px 12px;
    padding: 10px 14px;
}
.laca-dashboard-card .laca-post-list li {
    padding: 9px 0;
}
.laca-dashboard-card .laca-quick-search-input {
    min-height: 36px;
    padding: 8px 10px;
    border-radius: 8px;
}
.laca-dashboard-card #laca-notes-list {
    gap: 8px !important;
}
.laca-dashboard-card .laca-note {
    border-radius: 8px !important;
}
.laca-dashboard-card .laca-bsw__log {
    max-height: 220px;
}
.laca-dashboard-card .laca-cwv-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
.laca-overview-summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin: -2px 0 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f0f0f1;
}
.laca-overview-summary strong {
    display: block;
    color: #1d2327;
    font-size: 28px;
    line-height: 1;
}
.laca-overview-summary span,
.laca-overview-summary p {
    margin: 0;
    color: #646970;
    font-size: 12px;
}
.laca-overview-summary p {
    max-width: 520px;
    text-align: right;
}
.laca-overview-action-list,
.laca-overview-leads {
    margin: 0;
}
.laca-overview-action-list li,
.laca-overview-leads li {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin: 0;
    padding: 9px 0;
    border-bottom: 1px solid #f0f0f1;
}
.laca-overview-action-list li:last-child,
.laca-overview-leads li:last-child {
    border-bottom: 0;
}
.laca-overview-action-list__label,
.laca-overview-action-list__meta {
    display: inline-flex;
    align-items: center;
    gap: 7px;
}
.laca-overview-action-list__label {
    min-width: 0;
    color: #1d2327;
    font-weight: 600;
}
.laca-overview-action-list__label .dashicons {
    width: 17px;
    height: 17px;
    color: #646970;
    font-size: 17px;
}
.laca-overview-action-list__meta {
    flex: 0 0 auto;
    color: #646970;
    font-size: 12px;
}
.laca-overview-action-list__meta strong {
    color: #1d2327;
    font-size: 14px;
}
.laca-overview-action-list li.has-work .laca-overview-action-list__meta strong {
    color: #b32d2e;
}
.laca-overview-action-list li.is-clear .laca-overview-action-list__meta strong {
    color: #008a20;
}
.laca-overview-score {
    display: flex;
    align-items: center;
    gap: 13px;
    margin-bottom: 13px;
}
.laca-overview-score__number {
    display: flex;
    align-items: baseline;
    justify-content: center;
    width: 78px;
    min-width: 78px;
    height: 58px;
    border: 1px solid #dcdcde;
    border-radius: 8px;
    background: #fbfbfc;
    color: #1d2327;
    font-size: 26px;
    font-weight: 700;
}
.laca-overview-score__number span {
    color: #646970;
    font-size: 12px;
    font-weight: 600;
}
.laca-overview-score strong {
    display: block;
    margin-bottom: 3px;
}
.laca-overview-score p {
    margin: 0;
    color: #646970;
    font-size: 12px;
}
.laca-overview-leads a {
    font-weight: 700;
    text-decoration: none;
}
.laca-overview-leads span {
    display: block;
    margin-top: 3px;
    color: #646970;
    font-size: 12px;
}
.laca-overview-pill {
    display: inline-flex !important;
    align-items: center;
    min-height: 20px;
    padding: 0 7px;
    border-radius: 999px;
    background: #e7f5ee;
    color: #008a20 !important;
    font-size: 11px !important;
    font-weight: 700;
}
.laca-overview-footer {
    margin: 12px 0 0;
}
.laca-overview-empty {
    margin: 8px 0 0;
    color: #646970;
}
.laca-overview-posts {
    margin-bottom: 0;
}
.laca-overview-shortcuts {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}
.laca-overview-shortcuts a {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 36px;
    padding: 8px 10px;
    border: 1px solid #dcdcde;
    border-radius: 8px;
    background: #fff;
    color: #1d2327;
    font-weight: 600;
    text-decoration: none;
}
.laca-overview-shortcuts a:hover,
.laca-overview-shortcuts a:focus {
    border-color: #2271b1;
    color: #135e96;
    outline: none;
}
.laca-overview-shortcuts .dashicons {
    width: 17px;
    height: 17px;
    color: #646970;
    font-size: 17px;
}
@media (max-width: 960px) {
    .laca-dashboard-page__header {
        display: block;
    }
    .laca-dashboard-page__actions {
        margin-top: 12px;
        justify-content: flex-start;
    }
    .laca-dashboard-layout-status {
        text-align: left;
    }
    .laca-dashboard-grid {
        grid-template-columns: repeat(6, minmax(0, 1fr));
    }
    .laca-dashboard-card,
    .laca-dashboard-card--span-12,
    .laca-dashboard-card--span-8,
    .laca-dashboard-card--span-6,
    .laca-dashboard-card--span-4 {
        grid-column: 1 / -1;
    }
    .laca-dashboard-card--hero .lacadev-actions-list {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .laca-overview-summary {
        align-items: flex-start;
        display: block;
    }
    .laca-overview-summary p {
        margin-top: 8px;
        max-width: none;
        text-align: left;
    }
}
@media (max-width: 600px) {
    .laca-dashboard-card--hero .lacadev-actions-list {
        grid-template-columns: 1fr;
    }
    .laca-dashboard-card .laca-cwv-grid {
        grid-template-columns: 1fr;
    }
    .laca-overview-action-list li,
    .laca-overview-leads li {
        align-items: flex-start;
        display: block;
    }
    .laca-overview-action-list__meta {
        margin-top: 5px;
    }
    .laca-overview-score {
        align-items: flex-start;
    }
    .laca-overview-shortcuts {
        grid-template-columns: 1fr;
    }
}
';
    }
}
