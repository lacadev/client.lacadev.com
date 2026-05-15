<?php

declare(strict_types=1);

use App\Settings\Admin\AdminAccessPolicy;

test('AdminAccessPolicy builds denied plugin screens with optional theme editor', function (): void {
    assert_true(in_array('plugins', AdminAccessPolicy::deniedPluginScreens(false), true));
    assert_true(!in_array('theme-editor', AdminAccessPolicy::deniedPluginScreens(false), true));
    assert_true(in_array('theme-editor', AdminAccessPolicy::deniedPluginScreens(true), true));
});

test('AdminAccessPolicy exposes removed and denied settings screens', function (): void {
    assert_true(in_array('options-reading.php', AdminAccessPolicy::removedSettingsPages(), true));
    assert_true(in_array('options-permalink', AdminAccessPolicy::deniedSettingsScreens(), true));
    assert_true(in_array('toplevel_page_wpseo_dashboard', AdminAccessPolicy::deniedSettingsScreens(), true));
});

test('AdminAccessPolicy includes comment menu only when requested', function (): void {
    assert_true(!in_array('edit-comments.php', AdminAccessPolicy::hiddenMenuSlugs(false), true));
    assert_true(in_array('edit-comments.php', AdminAccessPolicy::hiddenMenuSlugs(true), true));
});
