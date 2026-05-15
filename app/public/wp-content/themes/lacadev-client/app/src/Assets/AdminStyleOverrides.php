<?php

namespace App\Assets;

/**
 * Shared WordPress admin UI overrides injected by the theme.
 */
final class AdminStyleOverrides
{
    public static function css(): string
    {
        return <<<'CSS'
        :root {
            --laca-admin-bg: #f3f5f9;
            --laca-admin-surface: #ffffff;
            --laca-admin-surface-muted: #f8fafc;
            --laca-admin-surface-strong: #eef2f7;
            --laca-admin-border: #dbe1ea;
            --laca-admin-border-strong: #c8d1de;
            --laca-admin-text: #0f172a;
            --laca-admin-text-muted: #64748b;
            --laca-admin-accent: #2563eb;
            --laca-admin-accent-strong: #1d4ed8;
            --laca-admin-accent-soft: rgba(37, 99, 235, 0.10);
            --laca-admin-danger: #dc2626;
            --laca-admin-success: #15803d;
            --laca-admin-warning: #b45309;
            --laca-admin-shadow: 0 18px 40px rgba(15, 23, 42, 0.07);
            --laca-admin-radius: 14px;
        }

        body.wp-admin,
        #wpcontent {
            background: var(--laca-admin-bg);
            color: var(--laca-admin-text);
        }

        body.wp-admin,
        body.wp-admin input,
        body.wp-admin button,
        body.wp-admin select,
        body.wp-admin textarea {
            font-family: inherit;
        }

        #wpadminbar {
            background: #0f172a !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        #wpadminbar .ab-item,
        #wpadminbar .ab-empty-item,
        #wpadminbar #wp-toolbar span.ab-label,
        #wpadminbar .ab-icon::before,
        #wpadminbar a.ab-item,
        #wpadminbar div.ab-item {
            color: rgba(255, 255, 255, 0.82) !important;
        }

        #wpadminbar .quicklinks > ul > li:hover > .ab-item,
        #wpadminbar .ab-top-menu > li:hover > .ab-item,
        #wpadminbar .ab-top-menu > li > .ab-item:focus {
            background: rgba(255, 255, 255, 0.08) !important;
            color: #fff !important;
        }

        #adminmenuback,
        #adminmenuwrap,
        #adminmenu {
            background: #111827 !important;
            border-right: 1px solid rgba(255, 255, 255, 0.06) !important;
        }

        #adminmenu li.menu-top:hover,
        #adminmenu li.opensub > a.menu-top,
        #adminmenu li > a.menu-top:focus {
            background: rgba(255, 255, 255, 0.06) !important;
            color: #fff !important;
        }

        #adminmenu a,
        #adminmenu div.wp-menu-image::before {
            color: rgba(255, 255, 255, 0.68) !important;
        }

        #adminmenu li.current a.menu-top,
        #adminmenu li.wp-has-current-submenu a.wp-has-current-submenu {
            background: rgba(255, 255, 255, 0.08) !important;
            border-left: 3px solid var(--laca-admin-accent);
            color: #fff !important;
            font-weight: 600;
        }

        #adminmenu .wp-submenu {
            background: #0f172a !important;
        }

        #adminmenu .wp-submenu a {
            color: rgba(255, 255, 255, 0.66) !important;
        }

        #adminmenu .wp-submenu li.current a,
        #adminmenu .wp-submenu a:hover {
            color: #fff !important;
        }

        #wpbody-content > .wrap,
        .auto-fold #wpbody-content > .wrap {
            max-width: 1440px;
            padding-top: 18px;
        }

        .wrap h1,
        .wrap h2,
        .wrap h3,
        .wrap .page-title-action,
        .cf-container__tabs-item,
        .lcf-tab-btn,
        .lcf-pv-tab {
            color: var(--laca-admin-text);
            letter-spacing: 0;
        }

        .page-title-action,
        .button,
        .button-secondary,
        .button-primary,
        #publish {
            border-radius: 10px !important;
            box-shadow: none !important;
            font-weight: 600;
            min-height: 38px;
            padding-inline: 14px !important;
        }

        .button-primary,
        #publish,
        .page-title-action {
            background: var(--laca-admin-accent) !important;
            border-color: var(--laca-admin-accent) !important;
            color: #fff !important;
        }

        .button-primary:hover,
        #publish:hover,
        .page-title-action:hover {
            background: var(--laca-admin-accent-strong) !important;
            border-color: var(--laca-admin-accent-strong) !important;
        }

        .button-secondary {
            border-color: var(--laca-admin-border-strong) !important;
            color: var(--laca-admin-text) !important;
        }

        .button-secondary:hover {
            border-color: var(--laca-admin-accent) !important;
            color: var(--laca-admin-accent) !important;
        }

        .notice,
        div.updated,
        div.error {
            background: var(--laca-admin-surface) !important;
            border: 1px solid var(--laca-admin-border) !important;
            border-left-width: 4px !important;
            border-radius: 12px !important;
            box-shadow: none !important;
        }

        .notice-success {
            border-left-color: var(--laca-admin-success) !important;
        }

        .notice-info {
            border-left-color: var(--laca-admin-accent) !important;
        }

        .notice-warning {
            border-left-color: var(--laca-admin-warning) !important;
        }

        .notice-error {
            border-left-color: var(--laca-admin-danger) !important;
        }

        .postbox,
        .stuffbox,
        .card,
        .cf-container-theme-options .cf-container__fields,
        .wp-list-table,
        .laca-cf-builder-shell,
        .laca-help-card,
        .laca-help-footer,
        .lacadev-stat-box,
        .lacadev-dashboard-grid .stat-item,
        .lacadev-btn-quick,
        .laca-todo-item {
            background: var(--laca-admin-surface) !important;
            border-radius: var(--laca-admin-radius) !important;
            border: 1px solid var(--laca-admin-border) !important;
            box-shadow: var(--laca-admin-shadow) !important;
        }

        .postbox-header,
        .handlediv,
        .cf-container-theme-options .cf-container__tabs,
        .lcf-tabs,
        .lcf-preview-switcher,
        .nav-tab-wrapper {
            background: var(--laca-admin-surface-muted) !important;
            border-bottom: 1px solid var(--laca-admin-border) !important;
        }

        .cf-container__tabs-item--current,
        .lcf-tab-btn.is-active,
        .lcf-pv-tab.is-active,
        .nav-tab-active {
            background: var(--laca-admin-surface) !important;
            color: var(--laca-admin-text) !important;
            border-color: var(--laca-admin-border) !important;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }

        .cf-container__tabs-item,
        .lcf-tab-btn,
        .lcf-pv-tab,
        .nav-tab {
            color: var(--laca-admin-text-muted) !important;
            font-weight: 600 !important;
            border-radius: 10px 10px 0 0 !important;
        }

        .nav-tab-wrapper {
            border: 0 !important;
            display: flex;
            gap: 8px;
            margin-bottom: 20px !important;
            padding: 8px !important;
        }

        .nav-tab {
            border: 1px solid transparent !important;
            margin-left: 0 !important;
            padding: 9px 14px !important;
        }

        input[type="text"],
        input[type="email"],
        input[type="url"],
        input[type="password"],
        input[type="number"],
        input[type="search"],
        textarea,
        select {
            border-color: var(--laca-admin-border-strong) !important;
            border-radius: 10px !important;
            box-shadow: none !important;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="url"]:focus,
        input[type="password"]:focus,
        input[type="number"]:focus,
        input[type="search"]:focus,
        textarea:focus,
        select:focus {
            border-color: var(--laca-admin-accent) !important;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12) !important;
        }

        .form-table th {
            color: var(--laca-admin-text);
            font-weight: 700;
            padding-top: 18px;
        }

        .form-table td,
        .description,
        .forminp p {
            color: var(--laca-admin-text-muted);
        }

        .wp-list-table thead th,
        .wp-list-table tfoot th {
            background: var(--laca-admin-surface-muted);
            color: var(--laca-admin-text-muted);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .wp-list-table tbody tr:hover td {
            background: rgba(15, 23, 42, 0.025);
        }

        .lacadev-dashboard-widget,
        .laca-help-wrap {
            color: var(--laca-admin-text);
        }

        .lacadev-stat-box .stat-label,
        .lacadev-dashboard-grid .stat-label,
        .laca-health-list .health-label,
        .laca-help-intro,
        .laca-help-card-content,
        .laca-help-footer,
        .laca-charts-footer {
            color: var(--laca-admin-text-muted) !important;
        }

        .lacadev-btn-quick:hover,
        .laca-todo-item:hover {
            border-color: var(--laca-admin-accent) !important;
            color: var(--laca-admin-accent) !important;
            transform: translateY(-1px);
        }

        .laca-help-header,
        .hub-section-title,
        .laca-help-card h3,
        .laca-chart-block h4 {
            color: var(--laca-admin-text) !important;
        }

        .laca-help-footer {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%) !important;
        }

        .swal2-popup,
        .alp-welcome-swal {
            background: #ffffff !important;
            border: 1px solid var(--laca-admin-border) !important;
            border-radius: 18px !important;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.16) !important;
            color: var(--laca-admin-text) !important;
        }

        .alp-welcome-swal {
            backdrop-filter: none !important;
            max-width: 420px !important;
            padding: 24px !important;
        }

        .alp-welcome-swal .alp-swal-title {
            color: var(--laca-admin-text) !important;
            font-size: 24px !important;
        }

        .alp-welcome-swal .alp-swal-msg {
            color: var(--laca-admin-text-muted) !important;
        }

        .alp-welcome-swal .alp-swal-icon {
            filter: none !important;
        }
CSS;
    }
}
