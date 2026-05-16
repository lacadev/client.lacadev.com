<?php
namespace App\Settings;

use App\Settings\ThemeControlTabs\Assets as ThemeControlAssets;
use App\Settings\ThemeControlTabs\AuthorTab;
use App\Settings\ThemeControlTabs\CtaTab;
use App\Settings\ThemeControlTabs\GeneralTab;
use App\Settings\ThemeControlTabs\PerformanceTab;
use App\Settings\ThemeControlTabs\SearchTab;
use App\Settings\ThemeControlTabs\SystemTab;

/**
 * ThemeControlCenter
 *
 * A single consolidated admin page ("Appearance > Theme Control") that
 * exposes every theme option category in one tabbed UI — replacing the
 * scattered individual Carbon Fields / wp-admin pages.
 *
 * Tabs are registered via the `lacadev/control-center/tabs` filter so
 * child themes and plugins can add their own sections without touching
 * this file.
 *
 * Tab definition:
 *   [
 *     'id'       => 'my_tab',          // unique slug
 *     'label'    => 'My Settings',     // sidebar label
 *     'icon'     => '⚙',              // emoji or dashicon class
 *     'render'   => callable,          // fn() — outputs HTML panel content
 *     'save'     => callable|null,     // fn(array $post_data) — processes save (optional)
 *     'cap'      => 'manage_options',  // capability required (optional)
 *   ]
 *
 * Save flow: each tab's `save` callable is called only when its own
 * `_laca_cc_tab` hidden field matches the submitted tab ID.
 * Each callable receives the raw `$_POST` array (already nonce-verified).
 *
 * @package App\Settings
 */
class ThemeControlCenter
{
    private const MENU_SLUG = 'lacadev-control-center';
    private const PARENT_SLUG = 'laca-admin';
    private const NONCE_KEY = 'laca_cc_save';

    public function init(): void
    {
        add_action('admin_menu',             [$this, 'registerPage'], 99);
        add_action('admin_menu',             [$this, 'hideAppearanceSubmenu'], 100);
        add_action('admin_enqueue_scripts',  [$this, 'enqueueAssets']);
        add_action('admin_init',             [$this, 'redirectLegacyPageUrl']);
        add_action('template_redirect',      [$this, 'redirectPrettyAdminPageUrl']);
        add_action('admin_post_laca_cc_save',[$this, 'handleSave']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Menu registration
    // ─────────────────────────────────────────────────────────────────────

    public function registerPage(): void
    {
        add_theme_page(
            'Laca Theme Settings',
            'Theme Settings',
            'read',
            self::MENU_SLUG,
            [$this, 'renderPage']
        );
    }

    public function hideAppearanceSubmenu(): void
    {
        remove_submenu_page('themes.php', self::MENU_SLUG);
    }

    public function redirectLegacyPageUrl(): void
    {
        global $pagenow;

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($pagenow !== 'admin.php' || $page !== self::MENU_SLUG) {
            return;
        }

        $args = [];
        if (isset($_GET['tab'])) {
            $args['tab'] = sanitize_key(wp_unslash($_GET['tab']));
        }
        if (isset($_GET['saved'])) {
            $args['saved'] = sanitize_key(wp_unslash($_GET['saved']));
        }

        wp_safe_redirect($this->getPageUrl($args));
        exit;
    }

    public function redirectPrettyAdminPageUrl(): void
    {
        $requestPath = isset($_SERVER['REQUEST_URI'])
            ? (string) parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH)
            : '';

        if ($requestPath === '') {
            return;
        }

        $requestPath = untrailingslashit($requestPath);
        if (!str_ends_with($requestPath, '/wp-admin/' . self::MENU_SLUG)) {
            return;
        }

        wp_safe_redirect($this->getPageUrl());
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Page render
    // ─────────────────────────────────────────────────────────────────────

    public function renderPage(): void
    {
        if (!$this->canAccessPage()) {
            wp_die(__('Bạn không có quyền truy cập trang này.'));
        }

        $tabs       = $this->getTabs();
        $activeTab  = sanitize_key($_GET['tab'] ?? array_key_first($tabs));
        if (!isset($tabs[$activeTab])) {
            $activeTab = array_key_first($tabs);
        }

        $saved = isset($_GET['saved']) && $_GET['saved'] === '1';
        ?>
        <div class="wrap laca-cc">
            <h1 class="laca-cc__heading">
                <span>Theme Settings</span>
                <span class="laca-cc__version">v<?php echo esc_html(wp_get_theme()->get('Version')); ?></span>
            </h1>

            <?php if ($saved): ?>
            <div class="notice notice-success is-dismissible">
                <p>✓ Đã lưu cài đặt thành công.</p>
            </div>
            <?php endif; ?>

            <main class="laca-cc__panel" id="laca-cc-panel">
                <?php
                $activeTabDef = $tabs[$activeTab] ?? null;
                if ($activeTabDef && is_callable($activeTabDef['render'] ?? null)):
                    $hasSave = is_callable($activeTabDef['save'] ?? null);
                    if ($hasSave): ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field(self::NONCE_KEY, '_laca_cc_nonce'); ?>
                        <input type="hidden" name="action"       value="laca_cc_save">
                        <input type="hidden" name="_laca_cc_tab" value="<?php echo esc_attr($activeTab); ?>">
                        <input type="hidden" name="_laca_cc_redirect"
                               value="<?php echo esc_url($this->getPageUrl(['tab' => $activeTab, 'saved' => '1'])); ?>">
                    <?php endif; ?>

                    <div class="laca-cc__panel-content">
                        <?php ($activeTabDef['render'])(); ?>
                    </div>

                    <?php if ($hasSave): ?>
                        <div class="laca-cc__panel-footer">
                            <?php submit_button('Lưu thay đổi', 'primary', 'submit', false); ?>
                        </div>
                    </form>
                    <?php endif; ?>

                <?php else: ?>
                    <p>Tab không tìm thấy.</p>
                <?php endif; ?>
            </main>
        </div><!-- .wrap -->
        <?php
    }

    // ─────────────────────────────────────────────────────────────────────
    // Save handler
    // ─────────────────────────────────────────────────────────────────────

    public function handleSave(): void
    {
        if (!check_admin_referer(self::NONCE_KEY, '_laca_cc_nonce')) {
            wp_die('Security check failed.');
        }
        if (!$this->canAccessPage()) {
            wp_die('Unauthorized.');
        }

        $tabId    = sanitize_key($_POST['_laca_cc_tab'] ?? '');
        $redirect = esc_url_raw($_POST['_laca_cc_redirect'] ?? $this->getPageUrl());
        $tabs     = $this->getTabs();

        if (isset($tabs[$tabId]) && is_callable($tabs[$tabId]['save'] ?? null)) {
            ($tabs[$tabId]['save'])($_POST);
        }

        wp_safe_redirect($redirect);
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Tab registry
    // ─────────────────────────────────────────────────────────────────────

    private function getTabs(): array
    {
        $tabs = $this->defaultTabs();
        return (array) apply_filters('lacadev/control-center/tabs', $tabs);
    }

    private function canAccessPage(): bool
    {
        return current_user_can('manage_options') || current_user_can('edit_theme_options');
    }

    private function getPageUrl(array $args = []): string
    {
        $url = admin_url('themes.php?page=' . rawurlencode(self::MENU_SLUG));

        if ($args === []) {
            return $url;
        }

        return add_query_arg($args, $url);
    }

    private function defaultTabs(): array
    {
        $generalTab = new GeneralTab();
        $ctaTab = new CtaTab();
        $authorTab = new AuthorTab();
        $performanceTab = new PerformanceTab();
        $searchTab = new SearchTab();
        $systemTab = new SystemTab();

        return [
            'general' => [
                'label' => 'Tổng quan',
                'icon'  => '🏠',
                'render'=> [$generalTab, 'render'],
                'save'  => [$generalTab, 'save'],
            ],
            'cta' => [
                'label' => 'Sticky CTA',
                'icon'  => '📣',
                'render'=> [$ctaTab, 'render'],
                'save'  => [$ctaTab, 'save'],
            ],
            'author' => [
                'label' => 'Author Profile',
                'icon'  => '👤',
                'render'=> [$authorTab, 'render'],
                'save'  => [$authorTab, 'save'],
            ],
            'performance' => [
                'label' => 'Performance',
                'icon'  => '⚡',
                'render'=> [$performanceTab, 'render'],
                'save'  => [$performanceTab, 'save'],
            ],
            'search' => [
                'label' => 'Smart Search',
                'icon'  => '🔍',
                'render'=> [$searchTab, 'render'],
                'save'  => null,
            ],
            'system' => [
                'label' => 'System Info',
                'icon'  => '🖥',
                'render'=> [$systemTab, 'render'],
                'save'  => null,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Assets
    // ─────────────────────────────────────────────────────────────────────

    public function enqueueAssets(string $hook): void
    {
        $allowedHooks = [
            'appearance_page_' . self::MENU_SLUG,
        ];

        if (!in_array($hook, $allowedHooks, true)) {
            return;
        }

        wp_add_inline_style('wp-admin', (new ThemeControlAssets())->inlineCss());
    }
}
