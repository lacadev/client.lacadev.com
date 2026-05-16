<?php

namespace App\Settings\ThemeControlTabs;

class SystemTab
{
    public function render(): void
    {
        global $wpdb;

        $theme  = wp_get_theme();
        $parent = wp_get_theme(get_template());
        ?>
        <table class="widefat striped" style="max-width:600px">
            <thead><tr><th>Key</th><th>Value</th></tr></thead>
            <tbody>
                <tr><td>Theme</td><td><?php echo esc_html($theme->get('Name') . ' v' . $theme->get('Version')); ?></td></tr>
                <tr><td>Parent theme</td><td><?php echo esc_html($parent->get('Name') . ' v' . $parent->get('Version')); ?></td></tr>
                <tr><td>WordPress</td><td><?php echo esc_html(get_bloginfo('version')); ?></td></tr>
                <tr><td>PHP</td><td><?php echo esc_html(PHP_VERSION); ?></td></tr>
                <tr><td>MySQL</td><td><?php echo esc_html($wpdb->db_version()); ?></td></tr>
                <tr><td>WP Memory limit</td><td><?php echo esc_html(WP_MEMORY_LIMIT); ?></td></tr>
                <tr><td>Debug mode</td><td><?php echo WP_DEBUG ? '<span style="color:orange">ON</span>' : '<span style="color:green">OFF</span>'; ?></td></tr>
                <tr><td>Home URL</td><td><?php echo esc_html(home_url('/')); ?></td></tr>
                <tr><td>Multisite</td><td><?php echo is_multisite() ? 'Yes' : 'No'; ?></td></tr>
                <tr><td>Object cache</td><td><?php echo wp_using_ext_object_cache() ? 'External (Redis/Memcached)' : 'DB transients'; ?></td></tr>
                <tr><td>Active plugins</td><td><?php echo count(get_option('active_plugins', [])); ?></td></tr>
            </tbody>
        </table>
        <?php
    }
}
