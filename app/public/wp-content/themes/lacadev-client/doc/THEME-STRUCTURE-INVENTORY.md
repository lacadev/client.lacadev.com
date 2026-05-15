# LacaDev Client Theme - Structure And Feature Inventory

Last updated: 2026-05-15

This document is a quick map of the current `lacadev-client` theme after the bootstrap cleanup. Use it as a manual checklist when reviewing future changes.

## 1. Main Entry Points

| Path | Responsibility |
| --- | --- |
| `theme/functions.php` | WordPress theme bootstrap, constants, Composer/WPEmerge boot, setup includes, dynamic CPT boot, DB schema install hooks. |
| `app/helpers.php` | Loads global helper functions, user/auth setup, admin UI setup, then boots immediate features through `FeatureRegistry`. |
| `app/hooks.php` | Registers WordPress actions/filters and delegates feature boot timing to `FeatureRegistry`. |
| `app/config.php` | WPEmerge route and middleware configuration. |
| `app/src/Bootstrap/FeatureRegistry.php` | Central manifest for feature/module bootstrapping and hook timing. |
| `theme/setup/*.php` | WordPress setup modules: assets, theme support, security, performance, PWA, Gutenberg blocks, reCAPTCHA, menus. |
| `resources/scripts` | Source JavaScript for frontend, admin, editor, login, and service worker. |
| `resources/styles` | Source SCSS/Tailwind styles for frontend, admin, editor, login. |
| `block-gutenberg` | Custom/synced Gutenberg block source. |
| `theme-server` | Server-side metadata/config files for release/update workflows. |

## 2. Runtime Boot Order

1. `theme/functions.php` defines directory constants and loads Composer autoload.
2. `theme/functions.php` loads `app/helpers.php`.
3. `app/helpers.php` loads helper files and calls `FeatureRegistry::bootImmediate()`.
4. `theme/functions.php` boots WPEmerge with `app/config.php`.
5. `theme/functions.php` loads `app/hooks.php`.
6. `app/hooks.php` registers feature boot callbacks on `init`.
7. `after_setup_theme` loads `theme/setup/*` modules.
8. `FeatureRegistry::bootDynamicPostTypes()` boots the dynamic CPT manager.
9. DB tables are installed only when `LACADEV_CLIENT_SCHEMA_VERSION` changes.

## 3. Feature Registry Groups

| Registry group | Current features |
| --- | --- |
| `immediate_constructors` | Required plugins, admin settings, auto-download images, theme settings, optimization settings. |
| `immediate_late_constructors` | LacaTools management experience, Smart Search REST endpoint. |
| `immediate_initializers` | Multi-step contact forms, context-aware CTA, author trust profile, recommendation engine, theme control center, editorial workflow, role-based admin UX. |
| `dynamic_post_type_constructors` | Dynamic CPT manager. |
| `admin_init_constructors` | Theme updater, block sync dashboard widget. |
| `admin_init_registrars` | Laca admin menu organizer. |
| `init_priority_five_static_registrars` | LacaDev tracker client. |
| `init_priority_five_constructors` | Block sync REST receiver. |
| `init_priority_one_initializers` | Maintenance mode manager. |
| `init_priority_one_constructors` | Custom login manager. |
| `init_default_initializers` | Contact form AJAX/shortcode, email log, related posts, exit intent popup, frontend chatbot. |
| `init_default_constructors` | Two-factor authentication. |
| `init_default_late_initializers` | Security manager. |

## 4. Existing Feature Inventory

| Area | Files/classes | What to check manually |
| --- | --- | --- |
| Theme setup | `theme/setup/theme-support.php`, `menus.php`, `sidebars.php`, `admin-ui.php` | Theme supports, menu locations, admin UI visibility. |
| Assets | `theme/setup/assets.php`, `app/src/Assets/*`, `app/src/Contracts/AssetHandles.php` | Frontend/admin/editor/login CSS and JS load with correct URLs, localized data, and dashboard chart payloads. |
| Shared support | `app/src/Support/ClientIpResolver.php` | Client IP resolution from Cloudflare, forwarded, client, and remote-address headers. |
| Performance | `theme/setup/performance.php`, `image-optimization.php`, `pwa.php`, `script-governance.php`, `app/src/Performance/ScriptGovernor.php` | Critical CSS, deferred scripts, WebP/AVIF, service worker, script governance. |
| Security | `theme/setup/security.php`, `app/src/Settings/Security/*` | Security admin page, custom login, 2FA, malware scan, file integrity scan, hidden user scan. |
| reCAPTCHA | `theme/setup/recaptcha.php` | Contact/login form verification and configured site keys. |
| SEO | `theme/setup/seo.php` | Meta/schema output if this setup file is enabled again. |
| Contact forms | `app/src/Features/ContactForm/*`, `app/src/Databases/ContactFormTable.php` | Form defaults, schema/admin sanitization, field rendering, shared CSS/JS renderers, shortcode rendering, multi-step flow, AJAX submit, email notifications, submissions table, CSV export. |
| Dynamic CPT | `app/src/Features/DynamicCPT/*` | CPT creation UI, generated archive/single templates, generated meta files, rewrite URLs. |
| Smart search | `app/src/Features/SmartSearch/SmartSearchEndpoint.php`, `resources/scripts/theme/ajax-search.js` | REST search index, cache busting, frontend search UI. |
| Content UX | `RelatedPosts`, `ContextAwareCta`, `AuthorTrustProfile`, `RecommendationEngine`, `ExitIntentPopup` | Single post content additions, CTA visibility, popup rules, recommendation output. |
| Admin UX | `ThemeControlCenter`, `RoleBasedAdminUx`, `EditorialWorkflow`, `LacaAdminMenuOrganizer`, `AdminAccessDeniedPage`, `AdminAccessPolicy`, `AdminDashboardIntroWidget`, `AdminOptionHtml`, `AdminMediaSupport` | Admin menu structure, tabbed theme settings, custom statuses, role restrictions, branded denial screen, reusable option snippets, dashboard intro widget, media upload helper behavior. |
| Management tools | `app/src/Settings/LacaTools/*` | Dashboard widgets, database cleaner, media audit, content audit, quick notes, performance budget widget, AI chat/translation tools. |
| Remote operations | `LacaDevTrackerClient`, `TrackerClientConfig`, `TrackerHttpTransport`, `TrackerQueuePolicy`, `TrackerHealthSummary`, `TrackerTimelinePresenter`, `TrackerShortcodeRenderer`, `RemoteUpdatePolicy`, `RemoteUpdatePreflight`, `RemoteUpdateHistory`, `MaintenanceSnapshot`, `SupportAttachmentFiles`, `SuspiciousFileScanner`, `TrackerFileIntegrity`, `ThemeUpdater`, `BlockSyncReceiver`, `BlockSyncWidget` | Tracker delivery, HTTP response normalization, local queue retry policy, tracker connection config, tracker health counters, public tracker shortcodes, remote update policy/preflight/history, maintenance snapshots, support attachments, file scans/integrity checks, block sync API, dashboard widget status. |
| Email log | `app/src/Settings/EmailLog/*` | Email capture table and admin display. |
| Maintenance mode | `MaintenanceModeManager`, `theme/maintenance.php` | Frontend lockout, admin bypass, maintenance template. |
| Gutenberg blocks | `theme/setup/gutenberg-blocks.php`, `block-gutenberg/*` | Block registration, category mapping, synced block rendering. |
| Templates | `theme/*.php`, `theme/layouts`, `theme/loop_templates`, `theme/page_templates` | Header/footer rendering, page template behavior, loop output, comments. |

## 5. Current Directory Shape

```text
lacadev-client/
├── app/
│   ├── config.php
│   ├── helpers.php
│   ├── hooks.php
│   ├── routes/
│   ├── helpers/
│   └── src/
│       ├── Assets/
│       ├── Bootstrap/
│       ├── Abstracts/
│       ├── Contracts/
│       ├── Databases/
│       ├── Features/
│       ├── Helpers/
│       ├── Models/
│       ├── Performance/
│       ├── PostTypes/
│       ├── Settings/
│       ├── Validators/
│       └── Widgets/
├── block-gutenberg/
├── doc/
├── resources/
│   ├── fonts/
│   ├── images/
│   ├── scripts/
│   └── styles/
├── tests/
├── theme/
│   ├── layouts/
│   ├── loop_templates/
│   ├── page_templates/
│   ├── setup/
│   └── *.php
├── theme-server/
├── vendor/
└── composer.json
```

## 6. Test Commands

```bash
composer test
php tests/run.php
```

The current tests are intentionally lightweight and run without booting WordPress. They verify:

| Test | Purpose |
| --- | --- |
| Feature registry exposes bootstrap groups | Prevents accidental deletion of wiring groups. |
| Registry classes resolve to files | Catches renamed/missing classes in the structural manifest. |
| Init hook priorities stay stable | Guards important runtime timing. |
| `RequirePlugins` has no file-scope self-instantiation | Prevents duplicate boot side effects during autoload. |
| WPEmerge config cleanup | Prevents empty provider and route placeholders from returning. |
| Removed placeholder files stay removed | Guards against reintroducing unused Module/View/Routing scaffolding. |
| Favicon fallback | Guards against outputting the empty `favicon.ico` without an SVG fallback. |
| Theme JS entrypoint asset cleanup | Guards against importing the empty favicon asset into the bundle. |
| Admin script data | Guards admin AJAX params and JS i18n keys. |
| Project chart data | Guards dashboard project status/month chart payloads. |
| Login asset data | Guards Carbon image normalization and localized login payload construction. |
| Asset loading rules | Guards deferred/async script handles and browser resource hints. |
| Asset preloader | Guards critical CSS/JS preload output and bundled font preload tags. |
| Inline asset helpers | Guards admin override CSS, editor CSS variables, login placeholders/logo CSS, and disabled reading-mode cleanup script. |
| Client IP resolver | Guards shared client IP priority, forwarded-header parsing, and invalid-value fallback. |
| Admin access denied page | Guards branded denial-screen markup and generated visual particles. |
| Admin access policy | Guards denied screen lists, removed settings pages, and hidden menu slugs. |
| Admin dashboard intro widget | Guards rendered author/contact dashboard widget markup. |
| Admin option HTML | Guards Block Sync and Tracker option page snippets. |
| Contact form defaults | Guards default fields and default email templates. |
| Contact form schema | Guards old/new field extraction, row conversion, multi-step splitting, conditions, sanitization, and scoped CSS. |
| Contact form admin sanitizer | Guards builder row/style payload cleanup before persistence. |
| Contact form CSV exporter | Guards CSV filenames, headers, read labels, field value expansion, and date formatting callbacks. |
| Contact form field renderer | Guards frontend field markup and step-marker skips. |
| Contact form frontend assets | Guards shared frontend CSS selectors and style output wrapper. |
| Contact form frontend scripts | Guards single-step and multi-step AJAX script rendering. |
| Contact form submission validator | Guards frontend submit validation, sanitization, conditions, and format errors. |
| Tracker client config | Guards tracker endpoint/secret option fallback and configured-state detection. |
| Tracker HTTP transport | Guards tracker JSON POST args, blocking response handling, non-blocking response handling, and non-2xx error messages. |
| Tracker queue policy | Guards local queue backoff windows, next-attempt formatting, and permanent-failure threshold. |
| Tracker health summary | Guards tracker queue/status counters and block diagnostics counters. |
| Tracker timeline presenter | Guards public-safe timeline messages, labels, dates, and remote update policy. |
| Tracker shortcode renderer | Guards support center and maintenance timeline shortcode markup. |
| Remote update preflight | Guards normalized preflight result shape without booting WordPress. |
| Remote update history | Guards remote update history normalization and capped prepends. |
| Maintenance snapshot | Guards captured remote-maintenance context for core updates. |
| Support attachment files | Guards single/multiple uploaded attachment normalization. |
| Suspicious file scanner | Guards root/uploads suspicious file detection and shell-pattern checks. |
| Tracker file integrity | Guards tracked file mtime comparison and baseline behavior. |
| `Crypto` encrypt/decrypt behavior | Guards sensitive-data helper round trips and invalid input behavior. |
| `DbVersionManager` schema version behavior | Guards installer execution, forced install, installed version reads, and reset behavior. |
| Admin media support | Guards custom upload mime types, help-guide screen detection, paste-image config, and upload filename normalization. |

## 7. Maintenance Rules

1. Add new feature bootstrapping to `FeatureRegistry` first.
2. Avoid `new SomeClass()` at file scope inside `app/src/*` class files.
3. Keep `functions.php` focused on bootstrap, constants, setup includes, and WordPress-required callbacks.
4. Keep heavy business logic out of `app/hooks.php`; use hooks there only to connect classes to WordPress.
5. When adding a feature, add a row to this inventory and update `FeatureRegistryTest` if the boot group changes.

## 8. Follow-up Cleanup Candidates

| Candidate | Why it still needs review |
| --- | --- |
| `theme/template-parts/*` references | This package references `template-parts` in several templates, while the files currently live in the sibling `lacadev-client-child` theme. Confirm whether `lacadev-client` must run standalone before changing or copying these parts. |
| Large admin feature classes | `LacaDevTrackerClient`, `ContactFormManager`, and `AdminSettings` are still large. `ContactFormAjaxHandler` is now much lighter after extracting frontend scripts and submission validation; tracker transport, queue policy, remote-update preflight, and maintenance snapshot logic are now extracted. Continue splitting tracker support-request/remote-update execution flow, contact form admin screens, and admin option registration one feature slice at a time with tests. |
| Empty local directories | `app/src/Module`, `app/src/Routing`, `app/src/View`, `resources/images/sprite`, `resources/vendor`, and `theme/setup/taxonomies` are empty in the working tree after cleanup. They are not tracked once their placeholder files are removed. |
