<?php
/**
 * CLI script ký số Ed25519 cho manifest phát hành Parent Theme lacadev-client.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$options = getopt('', [
    'slug::',
    'name::',
    'version:',
    'download-url:',
    'sha256:',
    'size:',
    'requires::',
    'requires-php::',
    'child-rebuild-recommended::',
    'last-updated::',
    'details-url::',
    'description::',
    'changelog-file::',
    'out:',
]);

$slug         = (string) ($options['slug'] ?? 'lacadev-client');
$name         = (string) ($options['name'] ?? 'La Cà Dev - Client');
$version      = trim((string) ($options['version'] ?? ''));
$downloadUrl  = trim((string) ($options['download-url'] ?? ''));
$sha256       = strtolower(trim((string) ($options['sha256'] ?? '')));
$size         = (int) ($options['size'] ?? 0);
$requires     = trim((string) ($options['requires'] ?? '6.4'));
$requiresPhp  = trim((string) ($options['requires-php'] ?? '8.1'));
$childRebuild = filter_var($options['child-rebuild-recommended'] ?? '1', FILTER_VALIDATE_BOOLEAN);
$lastUpdated  = trim((string) ($options['last-updated'] ?? gmdate('Y-m-d')));
$detailsUrl   = trim((string) ($options['details-url'] ?? 'https://lacadev.com'));
$description  = trim((string) ($options['description'] ?? 'Theme WordPress được code giữa những chuyến đi – bởi La Cà Dev.'));
$changelogPath = (string) ($options['changelog-file'] ?? '');
$outPath      = (string) ($options['out'] ?? '');

if ($version === '' || $downloadUrl === '' || !preg_match('/^[a-f0-9]{64}$/', $sha256) || $size <= 0 || $outPath === '') {
    fwrite(STDERR, "Missing or invalid required arguments for sign-manifest.php\n");
    exit(1);
}

if (!function_exists('sodium_crypto_sign_detached')) {
    fwrite(STDERR, "PHP sodium extension is required.\n");
    exit(1);
}

$rawKeyB64 = trim((string) getenv('LACA_RELEASE_SIGNING_KEY'));
if ($rawKeyB64 === '') {
    $keyFile = rtrim((string) getenv('HOME'), '/') . '/.laca-release/ed25519.keypair';
    if (!is_readable($keyFile)) {
        fwrite(STDERR, "Signing key not found in LACA_RELEASE_SIGNING_KEY env or {$keyFile}\n");
        exit(1);
    }
    $rawKeyB64 = trim((string) file_get_contents($keyFile));
}

$rawKey = base64_decode($rawKeyB64, true);
if ($rawKey === false) {
    fwrite(STDERR, "Signing key is not valid base64.\n");
    exit(1);
}

if (strlen($rawKey) === SODIUM_CRYPTO_SIGN_KEYPAIRBYTES) {
    $secretKey = sodium_crypto_sign_secretkey($rawKey);
    $publicKey = sodium_crypto_sign_publickey($rawKey);
} elseif (strlen($rawKey) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
    $secretKey = $rawKey;
    $publicKey = sodium_crypto_sign_publickey_from_secretkey($rawKey);
} else {
    fwrite(STDERR, "Signing key must decode to 64-byte Ed25519 secret key or 96-byte keypair.\n");
    exit(1);
}

$keyId = substr(hash('sha256', $publicKey), 0, 8);

/**
 * Chuyển CHANGELOG.md sang HTML gọn cho màn hình chi tiết cập nhật WordPress.
 */
function renderChangelogHtml(string $filePath): string
{
    if ($filePath === '' || !is_readable($filePath)) {
        return '';
    }

    $lines  = preg_split('/\R/', (string) file_get_contents($filePath)) ?: [];
    $html   = '';
    $inList = false;

    $formatInline = static function (string $text): string {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escaped = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped) ?? $escaped;
        return $escaped;
    };

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '## ')) {
            if ($inList) {
                $html  .= "</ul>\n";
                $inList = false;
            }
            $heading = substr($trimmed, 3);
            $html   .= '<h4>' . $formatInline($heading) . "</h4>\n";
        } elseif (str_starts_with($trimmed, '- ')) {
            if (!$inList) {
                $html  .= "<ul>\n";
                $inList = true;
            }
            $item  = substr($trimmed, 2);
            $html .= '<li>' . $formatInline($item) . "</li>\n";
        }
    }

    if ($inList) {
        $html .= "</ul>\n";
    }

    return trim($html);
}

$changelogHtml = renderChangelogHtml($changelogPath);

$canonical = implode("\n", [
    'laca-release-v1',
    $slug,
    $version,
    $sha256,
    (string) $size,
    $downloadUrl,
    $requires,
    $requiresPhp,
    $childRebuild ? '1' : '0',
]);

$signature = base64_encode(sodium_crypto_sign_detached($canonical, $secretKey));
sodium_memzero($secretKey);

$manifest = [
    'schema'                    => 2,
    'slug'                      => $slug,
    'name'                      => $name,
    'version'                   => $version,
    'download_url'              => $downloadUrl,
    'sha256'                    => $sha256,
    'size'                      => $size,
    'requires'                  => $requires,
    'requires_php'              => $requiresPhp,
    'child_rebuild_recommended' => $childRebuild,
    'last_updated'              => $lastUpdated,
    'details_url'               => $detailsUrl,
    'description'               => $description,
    'changelog'                 => $changelogHtml,
    'key_id'                    => $keyId,
    'signature'                 => $signature,
];

$outDir = dirname($outPath);
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

$json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false || file_put_contents($outPath, $json . "\n") === false) {
    fwrite(STDERR, "Failed to write manifest JSON to {$outPath}\n");
    exit(1);
}

echo "Signed manifest written to {$outPath} (key_id={$keyId})\n";
