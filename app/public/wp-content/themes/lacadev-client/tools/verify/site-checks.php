<?php
/**
 * Kiểm chứng toàn diện 7 hạng mục kiến trúc cho một site khách (chạy qua `wp eval-file`).
 *
 * Tham số truyền qua `$args`:
 *   $args[0]: Đường dẫn tới manifest local (`HP/theme-updates/lacadev-client.json`)
 *   $args[1]: Đường dẫn tới file JSON kết quả `wp laca-theme status --format=json` (tuỳ chọn)
 */

use App\Security\ReleaseVerifier;
use App\Settings\LacaDevTrackerClient;

$manifestPath = $args[0] ?? '/Users/lacadev/Documents/wordpress/lacadev/app/public/theme-updates/lacadev-client.json';
$cliStatusFile = $args[1] ?? '';

$checks = [];

// Đọc manifest local
$manifestRaw = is_readable($manifestPath) ? (string) file_get_contents($manifestPath) : '';
$manifestObj = $manifestRaw !== '' ? json_decode($manifestRaw) : null;
$expectedVer = (is_object($manifestObj) && !empty($manifestObj->version)) ? (string) $manifestObj->version : '';

// 1. Version parent == version trong manifest local
$parentTheme = wp_get_theme('lacadev-client/theme');
$parentVer   = $parentTheme->exists() ? (string) $parentTheme->get('Version') : '';
$checks['parent_version_match'] = [
    'pass'     => ($parentVer !== '' && $expectedVer !== '' && $parentVer === $expectedVer),
    'installed'=> $parentVer,
    'manifest' => $expectedVer,
];

// 2. ReleaseVerifier::verifyManifest() === true với manifest local
$manifestVerified = is_object($manifestObj) && class_exists(ReleaseVerifier::class)
    ? ReleaseVerifier::verifyManifest($manifestObj)
    : false;
$checks['manifest_signature'] = [
    'pass'   => $manifestVerified === true,
    'key_id' => is_object($manifestObj) ? ($manifestObj->key_id ?? null) : null,
];

// 3. wp laca-theme status --format=json: update_available=false, blocked=null
$cliStatus = null;
if ($cliStatusFile !== '' && is_readable($cliStatusFile)) {
    $rawCli = ltrim((string) file_get_contents($cliStatusFile), "\xEF\xBB\xBF \t\r\n");
    $cliStatus = json_decode($rawCli, true);
}
$checks['cli_theme_status'] = [
    'pass'             => is_array($cliStatus)
        && array_key_exists('update_available', $cliStatus)
        && $cliStatus['update_available'] === false
        && ($cliStatus['blocked'] ?? null) === null
        && empty($cliStatus['error']),
    'update_available' => is_array($cliStatus) ? ($cliStatus['update_available'] ?? null) : null,
    'blocked'          => is_array($cliStatus) ? ($cliStatus['blocked'] ?? null) : null,
    'error'            => is_array($cliStatus) ? ($cliStatus['error'] ?? null) : 'missing_cli_output',
];

// 4. Child active, Template: lacadev-client/theme, dist/build-meta.json có parent_version=3.9
$activeStylesheet = (string) get_stylesheet();
$activeTheme      = wp_get_theme($activeStylesheet);
$templateHeader   = (string) $activeTheme->get('Template');
$childRoot        = dirname(get_stylesheet_directory());
$buildMetaPath    = $childRoot . '/dist/build-meta.json';
$buildMeta        = is_readable($buildMetaPath) ? json_decode((string) file_get_contents($buildMetaPath), true) : null;
$metaParentVer    = is_array($buildMeta) ? (string) ($buildMeta['parent_version'] ?? '') : '';

$checks['child_active_and_build_meta'] = [
    'pass'           => ($activeStylesheet !== 'lacadev-client/theme')
        && ($templateHeader === 'lacadev-client/theme')
        && ($metaParentVer !== '' && $metaParentVer === $expectedVer),
    'stylesheet'     => $activeStylesheet,
    'template'       => $templateHeader,
    'meta_parent_ver'=> $metaParentVer,
];

// 5. Có đủ asset của parent; lacaParentAsset('admin.js')['path'] nằm trong thư mục parent
$parentRoot = realpath(dirname(get_template_directory())) ?: dirname(get_template_directory());
$requiredAssets = [
    'admin.js',
    'vendors.js',
    'login.js',
    'editor.js',
    'laca-seo.js',
    'laca-seo-editor.js',
    'styles/admin.css',
    'styles/login.css',
    'styles/editor.css',
    'styles/laca-seo.css',
    'styles/laca-seo-editor.css',
];
$missingAssets = [];
foreach ($requiredAssets as $relAsset) {
    $resolved = function_exists('lacaParentAsset') ? lacaParentAsset($relAsset) : null;
    if (!is_array($resolved) || empty($resolved['path']) || !is_file($resolved['path'])) {
        $missingAssets[] = $relAsset;
    }
}
$adminAsset     = function_exists('lacaParentAsset') ? lacaParentAsset('admin.js') : null;
$adminRealPath  = (is_array($adminAsset) && !empty($adminAsset['path'])) ? (realpath($adminAsset['path']) ?: $adminAsset['path']) : '';
$adminInParent  = $adminRealPath !== '' && str_starts_with($adminRealPath, trailingslashit($parentRoot) . 'dist/');

$checks['parent_assets'] = [
    'pass'            => empty($missingAssets) && $adminInParent,
    'missing'         => $missingAssets,
    'admin_in_parent' => $adminInParent,
    'admin_path'      => $adminRealPath,
];

// 6. Mọi block trong block-gutenberg của parent & child đã đăng ký và render không có Throwable / Warning
$registry = \WP_Block_Type_Registry::get_instance();
$blockRoots = array_unique(array_filter([
    dirname(get_template_directory()) . '/block-gutenberg',
    dirname(get_stylesheet_directory()) . '/block-gutenberg',
], 'is_dir'));

$scannedBlocks      = [];
$unregisteredBlocks = [];
$renderFailures     = [];

foreach ($blockRoots as $bRoot) {
    $rels = function_exists('lacadev_scan_block_relative_paths')
        ? lacadev_scan_block_relative_paths($bRoot)
        : [];
    foreach ($rels as $rel) {
        $bj = "{$bRoot}/{$rel}/block.json";
        if (!is_file($bj)) {
            continue;
        }
        $meta = json_decode((string) file_get_contents($bj), true);
        $name = is_array($meta) ? (string) ($meta['name'] ?? '') : '';
        if ($name !== '') {
            $scannedBlocks[$name] = $bj;
        }
    }
}

foreach ($scannedBlocks as $blockName => $bjPath) {
    if (!$registry->is_registered($blockName)) {
        $unregisteredBlocks[] = $blockName;
        continue;
    }

    $warnings = [];
    set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline) use (&$warnings): bool {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        if (in_array($errno, [E_WARNING, E_USER_WARNING, E_RECOVERABLE_ERROR], true)) {
            $warnings[] = "{$errstr} ({$errfile}:{$errline})";
        }
        return true;
    });

    ob_start();
    try {
        render_block([
            'blockName'   => $blockName,
            'attrs'       => [],
            'innerHTML'   => '',
            'innerBlocks' => [],
        ]);
    } catch (\Throwable $e) {
        $renderFailures[$blockName] = 'Throwable: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
    } finally {
        ob_end_clean();
        restore_error_handler();
    }

    if (!isset($renderFailures[$blockName]) && !empty($warnings)) {
        $renderFailures[$blockName] = 'Warnings: ' . implode(' | ', $warnings);
    }
}

$checks['blocks_registry_and_render'] = [
    'pass'         => !empty($scannedBlocks) && empty($unregisteredBlocks) && empty($renderFailures),
    'total_blocks' => count($scannedBlocks),
    'unregistered' => $unregisteredBlocks,
    'failures'     => $renderFailures,
];

// 7. Không có secret_key thô trong body của sendLogs khi đã ký (buildLogBody)
$trackerKeyPresent = class_exists(LacaDevTrackerClient::class) && LacaDevTrackerClient::getTrackerKey() !== '';
$projectIdPresent  = class_exists(LacaDevTrackerClient::class) && LacaDevTrackerClient::getProjectId() !== '';
$bodyCheckPass     = false;
$hasRawSecretInBody = null;

if (class_exists(LacaDevTrackerClient::class) && method_exists(LacaDevTrackerClient::class, 'buildLogBody')) {
    $sampleBody = LacaDevTrackerClient::buildLogBody([
        ['type' => 'verify_check', 'level' => 'info', 'content' => 'fleet check', 'meta' => []],
    ]);
    $hasRawSecretInBody = array_key_exists('secret_key', $sampleBody);
    $bodyCheckPass      = $trackerKeyPresent && $projectIdPresent && ($hasRawSecretInBody === false);
}

$checks['signed_log_no_raw_secret'] = [
    'pass'                  => $bodyCheckPass,
    'project_id_configured' => $projectIdPresent,
    'tracker_key_configured'=> $trackerKeyPresent,
    'has_secret_key_in_body'=> $hasRawSecretInBody,
];

$allPass = true;
foreach ($checks as $c) {
    if (empty($c['pass'])) {
        $allPass = false;
        break;
    }
}

echo json_encode([
    'site'     => home_url(),
    'all_pass' => $allPass,
    'checks'   => $checks,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
