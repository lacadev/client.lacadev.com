#!/usr/bin/env bash
# =============================================================================
# build-package.sh — Đóng gói tất định & ký số Ed25519 cho Parent Theme lacadev-client
# =============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PARENT_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"
THEMES_DIR="$(cd "${PARENT_DIR}/.." && pwd)"
CHILD_DIR="${THEMES_DIR}/lacadev-client-child"
REPO_ROOT="$(cd "${PARENT_DIR}/../../../../.." && pwd)"
WORKSPACE_DIR="$(cd "${REPO_ROOT}/.." && pwd)"
HUB_PUBLIC="${WORKSPACE_DIR}/lacadev/app/public"

VERSION=""
DOWNLOAD_URL=""
OUT_DIR="${PARENT_DIR}/releases"
CHILD_REBUILD_RECOMMENDED="1"
PUBLISH_LOCAL="0"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --version)
            VERSION="${2:-}"
            shift 2
            ;;
        --download-url)
            DOWNLOAD_URL="${2:-}"
            shift 2
            ;;
        --out)
            OUT_DIR="${2:-}"
            shift 2
            ;;
        --child-rebuild-recommended)
            CHILD_REBUILD_RECOMMENDED="${2:-1}"
            shift 2
            ;;
        --publish-local)
            PUBLISH_LOCAL="1"
            shift
            ;;
        *)
            echo "Unknown option: $1" >&2
            exit 1
            ;;
    esac
done

STYLE_CSS="${PARENT_DIR}/theme/style.css"
CHANGELOG_FILE="${PARENT_DIR}/CHANGELOG.md"

STYLE_VERSION="$(php -r '
    $css = file_get_contents($argv[1]);
    if (preg_match("/^\\s*\\*?\\s*Version\\s*:\\s*([^\\r\\n]+)/im", $css, $m)) {
        echo trim($m[1]);
    }
' "${STYLE_CSS}")"

if [[ -z "${VERSION}" ]]; then
    VERSION="${STYLE_VERSION}"
fi

if [[ -z "${VERSION}" ]]; then
    echo "Error: Could not determine theme version." >&2
    exit 1
fi

if [[ -z "${DOWNLOAD_URL}" ]]; then
    echo "Error: --download-url <url> is required." >&2
    exit 1
fi

IS_TEST_VERSION="0"
if [[ "${VERSION}" == *-test ]]; then
    IS_TEST_VERSION="1"
fi

# 1. Kiểm CHANGELOG.md và style.css khớp version (với bản phát hành chính thức)
if [[ "${IS_TEST_VERSION}" == "0" ]]; then
    if [[ "${VERSION}" != "${STYLE_VERSION}" ]]; then
        echo "Error: Requested version (${VERSION}) does not match theme/style.css (${STYLE_VERSION})." >&2
        exit 1
    fi
    if ! grep -Eq "^## ${VERSION}(\\s|$)" "${CHANGELOG_FILE}"; then
        echo "Error: CHANGELOG.md is missing section '## ${VERSION}'." >&2
        exit 1
    fi
fi

# 2. Build parent dist và Gutenberg blocks từ toolchain của child
echo "[1/7] Building parent assets and Gutenberg blocks..."
(
    cd "${CHILD_DIR}"
    if [[ -x "./node_modules/.bin/webpack" && -x "./node_modules/.bin/wp-scripts" ]]; then
        LACA_BUILD_TARGET=parent NODE_ENV=production ./node_modules/.bin/webpack --color --config resources/build/webpack.production.js
        ./node_modules/.bin/wp-scripts build --config resources/build/webpack.blocks.js
    else
        yarn build:parent
        yarn build:blocks
    fi
)

# 3. Tạo thư mục staging tạm và dọn tự động khi thoát
STAGE="$(mktemp -d)"
trap 'rm -rf "${STAGE}"' EXIT

echo "[2/7] Syncing files to clean staging directory..."
mkdir -p "${STAGE}/lacadev-client"
rsync -a \
    --exclude-from="${PARENT_DIR}/.distignore" \
    --exclude='/vendor/' \
    "${PARENT_DIR}/" "${STAGE}/lacadev-client/"

# WPEmerge\Config::get() trong theme/setup/theme-support.php:94 bắt buộc đọc config.json lúc runtime
# (ReadsJsonTrait::load() ném JsonFileNotFoundException nếu thiếu). Copy bản chuẩn từ config.json.dist.
if [[ -f "${PARENT_DIR}/config.json.dist" ]]; then
    cp "${PARENT_DIR}/config.json.dist" "${STAGE}/lacadev-client/config.json"
elif [[ -f "${PARENT_DIR}/config.json" ]]; then
    cp "${PARENT_DIR}/config.json" "${STAGE}/lacadev-client/config.json"
fi

# Với gói *-test phục vụ kiểm thử cập nhật/rollback: ghi version test vào bản sao trong STAGE
if [[ "${IS_TEST_VERSION}" == "1" ]]; then
    php -r '
        $p = $argv[1];
        $v = $argv[2];
        $c = file_get_contents($p);
        $c = preg_replace("/^(\\s*\\*?\\s*Version\\s*:\\s*)([^\\r\\n]+)/im", "\${1}" . $v, $c, 1);
        file_put_contents($p, $c);
    ' "${STAGE}/lacadev-client/theme/style.css" "${VERSION}"
fi

# 4. Cài vendor production (--no-dev) theo composer.lock trong staging rồi dọn file build
echo "[3/7] Installing production Composer dependencies in staging..."
cp "${PARENT_DIR}/composer.json" "${STAGE}/lacadev-client/composer.json"
cp "${PARENT_DIR}/composer.lock" "${STAGE}/lacadev-client/composer.lock"
if [[ -f "${PARENT_DIR}/scripts/patch-vendor.php" ]]; then
    mkdir -p "${STAGE}/lacadev-client/scripts"
    cp "${PARENT_DIR}/scripts/patch-vendor.php" "${STAGE}/lacadev-client/scripts/patch-vendor.php"
fi

composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-progress \
    --working-dir="${STAGE}/lacadev-client"

rm -rf \
    "${STAGE}/lacadev-client/composer.json" \
    "${STAGE}/lacadev-client/composer.lock" \
    "${STAGE}/lacadev-client/scripts"
find "${STAGE}/lacadev-client" \( -name '.DS_Store' -o -name '._*' -o -name '*.map' \) -delete

# 5. Kiểm tra cấu trúc bắt buộc có / bắt buộc không có
echo "[4/7] Verifying staged package layout..."
REQUIRED_PATHS=(
    "config.json"
    "theme/style.css"
    "vendor/autoload.php"
    "vendor/enshrined/svg-sanitize"
    "dist/admin.js"
    "dist/styles/admin.css"
    "dist/login.js"
)
for rel in "${REQUIRED_PATHS[@]}"; do
    if [[ ! -e "${STAGE}/lacadev-client/${rel}" ]]; then
        echo "Error: Missing required path in stage: lacadev-client/${rel}" >&2
        exit 1
    fi
done

FORBIDDEN_PATHS=(
    "doc"
    "tools"
    "scripts"
    "theme-server"
    "releases"
)
for rel in "${FORBIDDEN_PATHS[@]}"; do
    if [[ -e "${STAGE}/lacadev-client/${rel}" ]]; then
        echo "Error: Forbidden path present in stage: lacadev-client/${rel}" >&2
        exit 1
    fi
done

# 6. Kiểm tra cú pháp PHP cho toàn bộ file PHP ngoài vendor/
echo "[5/7] Linting staged PHP files..."
find "${STAGE}/lacadev-client" -path "${STAGE}/lacadev-client/vendor" -prune -o -type f -name '*.php' -print0 \
    | xargs -0 -n1 -P8 php -l >/dev/null

# 7. Đóng gói zip tất định theo SOURCE_DATE_EPOCH
echo "[6/7] Creating deterministic zip archive..."
if git -C "${REPO_ROOT}" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    SOURCE_DATE_EPOCH="${SOURCE_DATE_EPOCH:-$(git -C "${REPO_ROOT}" log -1 --format=%ct)}"
else
    SOURCE_DATE_EPOCH="${SOURCE_DATE_EPOCH:-1763000000}"
fi

TOUCH_STAMP="$(php -r 'echo gmdate("YmdHi.s", (int) $argv[1]);' "${SOURCE_DATE_EPOCH}")"
TZ=UTC find "${STAGE}/lacadev-client" -exec touch -h -t "${TOUCH_STAMP}" {} +

mkdir -p "${OUT_DIR}"
ZIP_PATH="${OUT_DIR}/lacadev-client-${VERSION}.zip"
MANIFEST_PATH="${OUT_DIR}/lacadev-client-${VERSION}.json"
STAGE_ZIP="${STAGE}/lacadev-client-${VERSION}.zip"

(
    cd "${STAGE}"
    find lacadev-client -type f | LC_ALL=C sort | TZ=UTC zip -X -q -@ "${STAGE_ZIP}"
)
mv -f "${STAGE_ZIP}" "${ZIP_PATH}"

# 8. Tính sha256, size và ký manifest Ed25519
echo "[7/7] Computing SHA-256 and signing release manifest..."
if command -v shasum >/dev/null 2>&1; then
    SHA256="$(shasum -a 256 "${ZIP_PATH}" | awk '{print $1}')"
else
    SHA256="$(sha256sum "${ZIP_PATH}" | awk '{print $1}')"
fi
SIZE_BYTES="$(wc -c < "${ZIP_PATH}" | tr -d '[:space:]')"

php "${SCRIPT_DIR}/sign-manifest.php" \
    --slug="lacadev-client" \
    --name="La Cà Dev - Client" \
    --version="${VERSION}" \
    --download-url="${DOWNLOAD_URL}" \
    --sha256="${SHA256}" \
    --size="${SIZE_BYTES}" \
    --requires="6.4" \
    --requires-php="8.1" \
    --child-rebuild-recommended="${CHILD_REBUILD_RECOMMENDED}" \
    --changelog-file="${CHANGELOG_FILE}" \
    --out="${MANIFEST_PATH}"

# 9. Xuất sang Hub local nếu có --publish-local
if [[ "${PUBLISH_LOCAL}" == "1" ]]; then
    if [[ ! -d "${HUB_PUBLIC}" ]]; then
        echo "Error: Local Hub directory not found at ${HUB_PUBLIC}" >&2
        exit 1
    fi
    mkdir -p "${HUB_PUBLIC}/theme-updates/private"
    cp "${ZIP_PATH}" "${HUB_PUBLIC}/theme-updates/private/lacadev-client.zip"
    cp "${MANIFEST_PATH}" "${HUB_PUBLIC}/theme-updates/lacadev-client.json"

    if [[ ! -f "${HUB_PUBLIC}/theme-updates/private/.htaccess" ]]; then
        printf "Require all denied\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n" > "${HUB_PUBLIC}/theme-updates/private/.htaccess"
    fi
    if [[ ! -f "${HUB_PUBLIC}/theme-updates/private/index.php" ]]; then
        printf "<?php\n// Silence is golden.\n" > "${HUB_PUBLIC}/theme-updates/private/index.php"
    fi
    echo "Published local package & manifest to ${HUB_PUBLIC}/theme-updates/"
fi

echo "Release ready:"
echo "  ZIP:      ${ZIP_PATH}"
echo "  MANIFEST: ${MANIFEST_PATH}"
echo "  SHA256:   ${SHA256}"
echo "  SIZE:     ${SIZE_BYTES} bytes"
