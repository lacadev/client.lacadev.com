#!/usr/bin/env bash
# =============================================================================
# verify-fleet.sh — Kiểm chứng đồng loạt 7 hạng mục kiến trúc cho toàn bộ site khách local
# =============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SITE_CHECKS="${SCRIPT_DIR}/site-checks.php"

WP="/Users/lacadev/Documents/wordpress"
MANIFEST="${WP}/lacadev/app/public/theme-updates/lacadev-client.json"
WPCLI="/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp"
LOCAL_SERVICES="/Users/lacadev/Library/Application Support/Local/lightning-services"
PHP83="${LOCAL_SERVICES}/php-8.3.17+1/bin/darwin-arm64/bin/php"
PHP85="${LOCAL_SERVICES}/php-8.5.12+0/bin/darwin-arm64/bin/php"

SITES=(
    "jackacademy|a6_CUzbW8|85"
    "lixroastery|nan2TUtPY|83"
    "nhakhoathienphuoc|4OJhLTw4w|83"
    "phucdainam|CkNBsbsb9|83"
)

TMP_DIR="/tmp/laca-verify-fleet"
mkdir -p "${TMP_DIR}"

FAIL_COUNT=0

printf "%-20s | %-6s | %-6s | %-6s | %-6s | %-6s | %-10s | %-6s | %-6s\n" \
    "SITE" "VER" "SIG" "CLI" "CHILD" "ASSETS" "BLOCKS" "NOKEY" "RESULT"
printf "%s\n" "---------------------+--------+--------+--------+--------+--------+------------+--------+-------"

for entry in "${SITES[@]}"; do
    IFS='|' read -r site site_id php_ver <<< "${entry}"
    if [[ "${php_ver}" == "85" ]]; then
        php_bin="${PHP85}"
    else
        php_bin="${PHP83}"
    fi
    sock="/Users/lacadev/Library/Application Support/Local/run/${site_id}/mysql/mysqld.sock"
    wp_root="${WP}/${site}/app/public"
    cli_out="${TMP_DIR}/${site}-cli.json"
    eval_out="${TMP_DIR}/${site}-checks.json"

    "${php_bin}" -d memory_limit=512M -d error_reporting=E_ERROR \
        -d "mysqli.default_socket=${sock}" \
        -d "pdo_mysql.default_socket=${sock}" \
        "${WPCLI}" --path="${wp_root}" laca-theme status --format=json > "${cli_out}" 2>/dev/null || true

    "${php_bin}" -d memory_limit=512M -d error_reporting=E_ERROR \
        -d "mysqli.default_socket=${sock}" \
        -d "pdo_mysql.default_socket=${sock}" \
        "${WPCLI}" --path="${wp_root}" eval-file "${SITE_CHECKS}" "${MANIFEST}" "${cli_out}" > "${eval_out}"

    row="$("${PHP83}" -r '
        $raw = ltrim((string) file_get_contents($argv[1]), "\xEF\xBB\xBF \t\r\n");
        $pos = strpos($raw, "{");
        if ($pos !== false) {
            $raw = substr($raw, $pos);
        }
        $d = json_decode($raw, true);
        if (!is_array($d) || !isset($d["checks"])) {
            echo "ERR|ERR|ERR|ERR|ERR|ERR|ERR|FAIL";
            exit(0);
        }
        $c = $d["checks"];
        $b = static fn($k) => !empty($c[$k]["pass"]) ? "PASS" : "FAIL";
        $blk = ($b("blocks_registry_and_render") === "PASS")
            ? ("PASS(" . ($c["blocks_registry_and_render"]["total_blocks"] ?? 0) . ")")
            : "FAIL";
        $res = !empty($d["all_pass"]) ? "PASS" : "FAIL";
        echo implode("|", [
            $b("parent_version_match"),
            $b("manifest_signature"),
            $b("cli_theme_status"),
            $b("child_active_and_build_meta"),
            $b("parent_assets"),
            $blk,
            $b("signed_log_no_raw_secret"),
            $res,
        ]);
    ' "${eval_out}")"

    IFS='|' read -r c_ver c_sig c_cli c_child c_assets c_blocks c_nokey c_res <<< "${row}"
    printf "%-20s | %-6s | %-6s | %-6s | %-6s | %-6s | %-10s | %-6s | %-6s\n" \
        "${site}" "${c_ver}" "${c_sig}" "${c_cli}" "${c_child}" "${c_assets}" "${c_blocks}" "${c_nokey}" "${c_res}"

    if [[ "${c_res}" != "PASS" ]]; then
        FAIL_COUNT=$((FAIL_COUNT + 1))
        echo "  -> Detail (${site}): $(cat "${eval_out}")" >&2
    fi
done

printf "%s\n" "---------------------+--------+--------+--------+--------+--------+------------+--------+-------"
if [[ "${FAIL_COUNT}" -eq 0 ]]; then
    echo "ALL PASS"
    exit 0
else
    echo "FAIL ${FAIL_COUNT}"
    exit 1
fi
