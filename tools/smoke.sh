#!/usr/bin/env bash
# ==============================================================================
# Smoke Test Script for LacaDev Hub & Client Repositories
# Usage: ./tools/smoke.sh [BASE_URL] [URLS_FILE]
# Example: ./tools/smoke.sh http://lacadev.local
#          ./tools/smoke.sh https://lacadev.com
# ==============================================================================

set -uo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BASE_URL="${1:-http://lacadev.local}"
URLS_FILE="${2:-${DIR}/smoke-urls.txt}"

# Remove trailing slash from BASE_URL
BASE_URL="${BASE_URL%/}"

if [[ ! -f "$URLS_FILE" ]]; then
    echo "Error: URLs file not found: $URLS_FILE" >&2
    exit 1
fi

TMP_DIR="$(mktemp -d /tmp/smoke-test.XXXXXX)"
trap 'rm -rf "$TMP_DIR"' EXIT

TOTAL=0
PASSED=0
FAILED=0

echo "=============================================================================="
echo " SMOKE TEST: $BASE_URL"
echo " URLs File : $URLS_FILE"
echo "=============================================================================="
printf "%-35s | %-8s | %-6s | %-8s | %-8s | %-8s | %-6s\n" \
    "Path" "Expected" "HTTP" "Size(B)" "Tag" "Clean" "Result"
echo "------------------------------------------------------------------------------"

while IFS= read -r line || [[ -n "$line" ]]; do
    # Trim leading/trailing whitespace
    line="$(echo "$line" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"
    
    # Skip comments and empty lines
    [[ -z "$line" || "$line" =~ ^# ]] && continue

    ((TOTAL++))

    # Read path and expected code
    read -r path expected_code <<< "$line"
    expected_code="${expected_code:-200}"

    full_url="${BASE_URL}${path}"
    body_file="${TMP_DIR}/body_${TOTAL}.tmp"

    # Make request
    http_code=$(curl -s -S -k -L \
        -o "$body_file" \
        -w "%{http_code}" \
        --max-time 15 \
        "$full_url" 2>/dev/null || echo "000")

    size=$(wc -c < "$body_file" 2>/dev/null | tr -d ' ' || echo "0")

    is_xml=false
    if [[ "$path" == *.xml* ]]; then
        is_xml=true
    fi

    # Check status code
    code_ok=false
    if [[ "$http_code" == "$expected_code" ]]; then
        code_ok=true
    fi

    # Check body size
    size_ok=false
    if $is_xml; then
        if (( size >= 200 )); then
            size_ok=true
        fi
    else
        if (( size >= 2000 )); then
            size_ok=true
        fi
    fi

    # Check closing tag / XML root
    tag_ok=false
    if $is_xml; then
        if grep -qiE "<sitemapindex|<urlset" "$body_file" 2>/dev/null; then
            tag_ok=true
        fi
    else
        if grep -qi "</html>" "$body_file" 2>/dev/null; then
            tag_ok=true
        fi
    fi

    # Check for PHP errors / warnings
    clean_ok=true
    if grep -qiE "Fatal error|critical error|Parse error|Warning:</b>|<b>Warning</b>:|PHP Warning:" "$body_file" 2>/dev/null; then
        clean_ok=false
    fi

    # Overall line result
    if $code_ok && $size_ok && $tag_ok && $clean_ok; then
        result="PASS"
        ((PASSED++))
    else
        result="FAIL"
        ((FAILED++))
    fi

    tag_str="FAIL"
    $tag_ok && tag_str="OK"

    clean_str="FAIL"
    $clean_ok && clean_str="OK"

    printf "%-35s | %-8s | %-6s | %-8s | %-8s | %-8s | %-6s\n" \
        "$path" "$expected_code" "$http_code" "$size" "$tag_str" "$clean_str" "$result"

done < "$URLS_FILE"

echo "------------------------------------------------------------------------------"
echo " Summary: Total: $TOTAL | Passed: $PASSED | Failed: $FAILED"
echo "=============================================================================="

if (( FAILED > 0 )); then
    exit 1
fi

exit 0
