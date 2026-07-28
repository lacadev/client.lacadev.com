<?php

namespace App\Settings;

/**
 * BlockCatalogProvider
 *
 * Phục vụ danh mục Gutenberg block (metadata + preview.png + toàn bộ file
 * nguồn) đọc-chỉ (read-only) cho hub (lacadev.com), để hub cache lại và cho
 * site khách hàng duyệt/yêu cầu đồng bộ từng block.
 *
 * Quét cả block-gutenberg/ của theme cha (lacadev-client) lẫn theme con
 * (lacadev-client-child) — nếu trùng tên, ưu tiên bản ở theme con (coi như
 * bản mới nhất/tùy biến).
 *
 * Endpoint:
 *   GET /wp-json/lacadev/v1/blocks-catalog          — danh sách block + metadata + preview
 *   GET /wp-json/lacadev/v1/blocks-catalog/files?block=... — toàn bộ file nguồn của 1 block (base64)
 *
 * `block` (cả ở listBlocks()'s field "path" lẫn query param của getBlockFiles())
 * là "block relative path" — tên block phẳng (không có "/") HOẶC
 * "{bucket}/{block}" nếu block nằm trong 1 thư mục bucket theo site. Nhận
 * qua QUERY PARAM (không phải URL path segment) để tránh rủi ro web server
 * xử lý sai ký tự "/" đã encode (%2F) trong path segment.
 *
 * Xác thực: header X-Laca-Catalog-Key, so khớp option `laca_catalog_key`
 * (khác với `laca_sync_key` của BlockSyncReceiver — 2 vai trò khác nhau:
 * BlockSyncReceiver NHẬN block được đẩy tới, còn provider này CUNG CẤP
 * block cho hub đọc).
 */
class BlockCatalogProvider
{
    private const NAMESPACE  = 'lacadev/v1';
    private const ROUTE      = 'blocks-catalog';
    private const KEY_OPTION = 'laca_catalog_key';

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::ROUTE, [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'listBlocks'],
            'permission_callback' => '__return_true', // Auth được xử lý trong callback
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::ROUTE . '/files', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getBlockFiles'],
            'permission_callback' => '__return_true',
            'args'                => [
                'block' => ['required' => true],
            ],
        ]);
    }

    // =========================================================================
    // REST CALLBACKS
    // =========================================================================

    public function listBlocks(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->authenticate($request)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Catalog Key không hợp lệ.'], 401);
        }

        $blocks = [];

        // Quét theme cha trước, theme con sau — nếu trùng tên, bản con ghi
        // đè bản cha (coi bản con là mới nhất/tùy biến).
        foreach ($this->resolveBlockDirs() as $dir) {
            foreach ($this->scanBlockDirs($dir) as $blockDir) {
                $data = json_decode((string) file_get_contents("{$blockDir}/block.json"), true);
                if (!is_array($data)) {
                    continue;
                }

                $name = basename($blockDir);
                // Relative path (có thể chứa "/" nếu block nằm trong 1 bucket
                // theo site) — dùng làm định danh DUY NHẤT, tránh 2 block
                // trùng tên lá ở 2 bucket khác nhau ghi đè lẫn nhau.
                $path = ltrim(substr($blockDir, strlen($dir)), '/');

                $blocks[$path] = [
                    'name'        => $name,
                    'path'        => $path,
                    'title'       => $data['title'] ?? $name,
                    'description' => $data['description'] ?? '',
                    'version'     => $data['version'] ?? '1.0.0',
                    'preview'     => $this->encodePreview($blockDir),
                ];
            }
        }

        ksort($blocks);

        return new \WP_REST_Response([
            'success' => true,
            'blocks'  => array_values($blocks),
        ], 200);
    }

    public function getBlockFiles(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->authenticate($request)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Catalog Key không hợp lệ.'], 401);
        }

        $blockName = self::sanitizeBlockRelativePath((string) ($request->get_param('block') ?? ''));
        $blockDir  = $this->findBlockDir($blockName);

        if (!$blockDir) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Không tìm thấy block.'], 404);
        }

        $version       = '1.0.0';
        $blockJsonPath = "{$blockDir}/block.json";
        if (file_exists($blockJsonPath)) {
            $json    = json_decode((string) file_get_contents($blockJsonPath), true);
            $version = $json['version'] ?? '1.0.0';
        }

        $files = [];
        $this->encodeDirectoryFiles($blockDir, $blockDir, $files);

        if (empty($files)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Không có file trong block.'], 404);
        }

        return new \WP_REST_Response([
            'success'    => true,
            'block_name' => $blockName,
            'path'       => $blockName,
            'version'    => $version,
            'files'      => $files,
        ], 200);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function authenticate(\WP_REST_Request $request): bool
    {
        $storedKey  = get_option(self::KEY_OPTION, '');
        $requestKey = $request->get_header('X-Laca-Catalog-Key') ?? '';

        if (empty($storedKey) || empty($requestKey)) {
            return false;
        }

        return hash_equals($storedKey, $requestKey);
    }

    /**
     * Trả về danh sách thư mục block-gutenberg cần quét, theo thứ tự
     * cha → con (để vòng lặp gọi sau — theme con — ghi đè đúng ý nếu trùng tên).
     */
    private function resolveBlockDirs(): array
    {
        $dirs = [];

        $parent = dirname(get_template_directory()) . '/block-gutenberg';
        if (is_dir($parent)) {
            $dirs[] = $parent;
        }

        $child = dirname(get_stylesheet_directory()) . '/block-gutenberg';
        if (is_dir($child) && $child !== $parent) {
            $dirs[] = $child;
        }

        return $dirs;
    }

    private function findBlockDir(string $blockName): ?string
    {
        // Quét theo thứ tự con trước (ưu tiên bản con nếu trùng tên ở cả 2 nơi)
        foreach (array_reverse($this->resolveBlockDirs()) as $dir) {
            foreach ($this->scanBlockDirs($dir) as $blockDir) {
                $path = ltrim(substr($blockDir, strlen($dir)), '/');
                if ($path === $blockName) {
                    return $blockDir;
                }
            }
        }

        return null;
    }

    /**
     * Làm sạch "block relative path" — tên block phẳng (không "/") hoặc
     * "{bucket}/{block}" (đúng 1 dấu "/"). Dùng thay cho sanitize_key() ở
     * mọi nơi nhận định danh block từ request, vì sanitize_key() xoá sạch
     * dấu "/" và sẽ phá path có bucket.
     */
    private static function sanitizeBlockRelativePath(string $raw): string
    {
        $raw = strtolower(trim($raw));
        $segments = array_filter(explode('/', $raw), static fn ($s) => $s !== '');
        $segments = array_map(static fn ($s) => preg_replace('/[^a-z0-9_-]/', '', $s), $segments);
        $segments = array_filter($segments, static fn ($s) => $s !== '' && $s !== '.' && $s !== '..');
        $segments = array_slice($segments, 0, 2);

        return implode('/', $segments);
    }

    /**
     * Quét 1 thư mục block-gutenberg, trả về absolute path của từng block
     * tìm được — hỗ trợ song song 2 dạng: block nằm phẳng ngay dưới root
     * (chưa phân loại site) VÀ block nằm trong 1 thư mục "bucket" theo site
     * (vd nhakhoathienphuoc-blocks-site/), lồng đúng 1 cấp.
     *
     * Quy ước: 1 thư mục có block.json ngay bên trong = 1 block; 1 thư mục
     * KHÔNG có block.json bên trong = 1 bucket, quét thêm 1 cấp con để tìm
     * block (không hỗ trợ lồng sâu hơn 1 cấp).
     *
     * @return string[]
     */
    private function scanBlockDirs(string $root): array
    {
        $blockDirs = [];
        $entries   = scandir($root) ?: [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $entryPath = "{$root}/{$entry}";
            if (!is_dir($entryPath)) {
                continue;
            }

            if (file_exists("{$entryPath}/block.json")) {
                $blockDirs[] = $entryPath;
                continue;
            }

            foreach ((scandir($entryPath) ?: []) as $subEntry) {
                if ($subEntry === '.' || $subEntry === '..') {
                    continue;
                }

                $subEntryPath = "{$entryPath}/{$subEntry}";
                if (is_dir($subEntryPath) && file_exists("{$subEntryPath}/block.json")) {
                    $blockDirs[] = $subEntryPath;
                }
            }
        }

        return $blockDirs;
    }

    private function encodePreview(string $blockDir): ?string
    {
        $previewPath = "{$blockDir}/preview.png";
        if (!file_exists($previewPath)) {
            return null;
        }

        return base64_encode((string) file_get_contents($previewPath));
    }

    /**
     * Đệ quy đọc tất cả files trong thư mục, encode base64 — cùng logic với
     * BlockSyncSender::encodeDirectoryFiles() bên hub, để tương thích trực
     * tiếp với BlockSyncReceiver::writeBlockFiles() phía site khách.
     */
    private function encodeDirectoryFiles(string $baseDir, string $currentDir, array &$files): void
    {
        $items = scandir($currentDir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (in_array($item, ['node_modules', '.git', '.DS_Store'], true)) {
                continue;
            }

            $path         = "{$currentDir}/{$item}";
            $relativePath = ltrim(str_replace($baseDir, '', $path), '/');

            if (is_dir($path)) {
                $this->encodeDirectoryFiles($baseDir, $path, $files);
            } elseif (is_file($path)) {
                if (str_ends_with($item, '.map')) {
                    continue;
                }
                $files[$relativePath] = base64_encode((string) file_get_contents($path));
            }
        }
    }

    // =========================================================================
    // STATIC: AUTO-GENERATE CATALOG KEY
    // =========================================================================

    public static function ensureApiKey(): string
    {
        $key = get_option(self::KEY_OPTION, '');
        if (empty($key)) {
            $key = wp_generate_uuid4();
            update_option(self::KEY_OPTION, $key);
        }
        return $key;
    }
}
