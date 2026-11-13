#!/usr/bin/env bash
# =============================================================================
# Wrapper tương thích ngược — chuyển tiếp sang tools/release/build-package.sh
#
# Cách dùng mới:
#   bash tools/release/build-package.sh \
#     --download-url https://lacadev.com/wp-json/laca/v1/theme-package/lacadev-client \
#     --child-rebuild-recommended 1
# =============================================================================
set -euo pipefail

THEME_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
echo "Lưu ý: build-release.sh đã chuyển sang tools/release/build-package.sh (đóng gói tất định + ký Ed25519)." >&2
exec "${THEME_DIR}/tools/release/build-package.sh" "$@"
