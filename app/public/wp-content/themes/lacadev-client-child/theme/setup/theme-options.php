<?php
/**
 * Child Theme Options
 *
 * Register child-only Carbon Fields tab when the project actually defines fields.
 *
 * @package LacaDevClientChild
 */

add_action('lacadev/theme_options/register_child_tabs', function ($optionsPage) {
    if (!$optionsPage) {
        return;
    }

    $fields = apply_filters('lacadev_child_theme_options_fields', []);

    if ($fields === []) {
        return;
    }

    $optionsPage->add_tab(__('Tuỳ chỉnh giao diện (Child)', 'laca'), $fields);
});
