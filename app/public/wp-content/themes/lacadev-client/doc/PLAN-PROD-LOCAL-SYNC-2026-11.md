# PLAN — Đồng bộ Production → Local: kéo database, phát hiện & kéo file lệch (11/2026)

> Viết plan: Claude Code · Làm: Antigravity · Review: Claude Code
> Bối cảnh (đã xác nhận với chủ dự án):
> - Mỗi site là 1 repo git riêng, cấu trúc Local by Flywheel: repo root = site root (`<site>/`), WordPress ở `<site>/app/public/`.
> - Deploy: `.github/workflows/deploy.yml` đẩy `wp-content/themes` + `wp-content/plugins` lên cPanel bằng FTP, sau đó gửi manifest cho `DiscordHub/FileWatch/DeployReceiver` (HMAC). `FileWatch/Scanner` (cron 15') đã phát hiện file thêm/sửa/xoá ngoài luồng deploy và báo Discord.
> - Hosting cPanel **có SSH**. Nhiều khách: có khách tự quản lý (tự cập nhật plugin trong wp-admin), có khách do chủ dự án quản lý toàn bộ. Dung lượng DB mỗi site khác nhau (có site lớn).
> - Rủi ro đang tồn tại: ai đó cập nhật plugin trên production → lần deploy sau FTP đè file plugin cũ trong git lên → plugin lẫn 2 phiên bản, có thể sập site.
>
> Mục tiêu:
> 1. **Kéo DB production về local bằng 1 thao tác** (1 lệnh / 1 nút trong VS Code), an toàn, chạy được với DB lớn.
> 2. **Biết production lệch gì so với git** (file + phiên bản plugin/core) và **kéo về** để commit.
> 3. **Chặn deploy đè** lên thay đổi chưa kéo về.
> 4. Local hiển thị đủ ảnh mà không phải tải toàn bộ `uploads/`.
> 5. Có chế độ khoá sửa file trên production cho site do chủ dự án quản lý.

---

## 0. Luật bắt buộc

1. Giữ luật mục 0 của các plan trước (không xoá file của dự án, không commit/push, không cài package bằng yarn/composer, i18n `__()` domain `laca` cho chữ hiển thị trong wp-admin, `cmp` A/B sau bước cuối…). Chữ in ra terminal của lệnh CLI: tiếng Việt, đi qua `__()` khi có thể.
2. **Phạm vi file**: phần chạy trong WordPress đặt trong theme (dùng chung, A = B giống hệt). Phần cấp repo (`.github/workflows/*`, `.vscode/tasks.json`, file cấu hình mẫu, `.gitignore`) **không sửa trực tiếp ở repo nào** — chỉ tạo **mẫu** trong theme `tools/sync-templates/` và lệnh `wp laca sync-init` để chép mẫu vào repo (không ghi đè file đã có; in diff gợi ý nếu khác). Riêng repo `lacadev-client` được chạy `sync-init` để thử (xem mục 8).
3. **Không bao giờ ghi lên production.** Mọi lệnh với production chỉ ĐỌC (export DB ra stdout, `rsync` chiều prod → local, `wp plugin list`…). Không có lệnh push DB/file local → production trong plan này.
4. **Lệnh làm thay đổi local** (import DB, ghi file) bắt buộc: chỉ chạy khi `wp_get_environment_type() === 'local'` (hoặc `development`) VÀ site URL local khác URL production; tự sao lưu trước; hỏi xác nhận (bỏ qua bằng `--yes`).
5. **Không lưu bí mật trong repo**: host/user/port/path SSH đặt trong file cấu hình bị gitignore; chỉ commit file `*.example`. Khoá SSH dùng ssh-agent/`~/.ssh/config` của máy, không đọc/ghi private key.
6. Gọi lệnh ngoài (`ssh`, `rsync`, `gzip`, `mysql`, `wp`) bằng `proc_open` với từng tham số qua `escapeshellarg`; không nội suy chuỗi người dùng vào shell. Stream dữ liệu (không đọc cả DB vào RAM).
7. **Kiểm thử không được phá dữ liệu thật** (mục 8): KHÔNG import DB vào `lacadev`, `lacadev-client`, `jackacademy` hay site thật nào. Chỉ import vào site test do chủ dự án tạo.

---

## 1. Cấu hình đồng bộ

- File `laca-sync.yml` ở **repo root** (gitignore), mẫu `laca-sync.example.yml` commit được:
  ```yaml
  production:
    ssh: "user@host.example.com:22"      # hoặc tên Host trong ~/.ssh/config
    path: "/home/user/public_html"       # thư mục WordPress trên server (chứa wp-config.php)
    url: "https://example.com"            # home_url production
    wp: "wp"                              # lệnh wp-cli trên server (vd "php ~/bin/wp-cli.phar")
  local:
    url: ""                               # để trống = tự đọc home_url() của site local
  db:
    exclude_tables: ["wflogs", "actionscheduler_logs", "laca_project_logs"]  # hậu tố bảng (tự thêm prefix)
    exclude_data_only: ["laca_email_log", "laca_login_log", "laca_contact_submissions"] # giữ cấu trúc, bỏ dữ liệu
    anonymize: false                      # true = ẩn danh dữ liệu cá nhân sau khi kéo (mục 3.6)
  files:
    paths: ["wp-content/themes", "wp-content/plugins", "wp-content/mu-plugins"]
    exclude: ["node_modules", ".git", "*.log", "cache", "wflogs", "upgrade", "uploads"]
  media:
    proxy: true                           # mục 6
  ```
- Tìm file theo thứ tự: biến môi trường `LACA_SYNC_CONFIG` → `ABSPATH/../../laca-sync.yml` → `ABSPATH/../laca-sync.yml`. Đọc YAML bằng parser của WP-CLI (`Spyc`/`WP_CLI\Utils` — kiểm tra cái có sẵn trong WP-CLI, không thêm package).
- `wp laca sync-check`: kiểm cấu hình + kết nối: SSH vào được (`ssh -o BatchMode=yes … true`), `wp` chạy được trên server (`wp --info`), đúng thư mục (`wp option get home` khớp `production.url`), có `rsync`/`gzip`/`mysqldump` (hoặc `wp db export` dùng được), dung lượng DB production (`wp db size --tables` → tổng), dung lượng trống ổ local. In bảng OK/LỖI + cách sửa. Nếu server không có wp-cli → in hướng dẫn tải `wp-cli.phar` vào `~/bin` (không tự cài).

---

## 2. Lệnh WP-CLI trong theme

Class `app/src/Cli/LacaSyncCommand.php` (+ thư mục `app/src/Cli/Sync/` cho các phần), đăng ký `WP_CLI::add_command('laca', …)` chỉ khi `defined('WP_CLI') && WP_CLI` (cùng chỗ theme đang đăng ký `laca-discord`). Lệnh con:

| Lệnh | Việc |
|---|---|
| `wp laca sync-check` | Mục 1 |
| `wp laca pull-db [--yes] [--anonymize] [--no-backup] [--tables=a,b]` | Mục 3 |
| `wp laca drift [--format=table\|json]` | Mục 4.1 |
| `wp laca pull-files [--path=wp-content/plugins/x] [--yes] [--delete]` | Mục 4.2 |
| `wp laca pull-media [--since=30d] [--yes]` | Tải `uploads` có chọn lọc (tuỳ chọn, mục 6.3) |
| `wp laca local-safe` | Mục 3.5, chạy riêng được |
| `wp laca restore-db [--file=…]` | Khôi phục bản sao lưu local gần nhất |
| `wp laca sync-init` | Chép mẫu cấp repo (mục 7) |

Mỗi lệnh in tiến độ theo bước, thời gian, dung lượng; lỗi ở bước nào thì dừng và in cách khôi phục.

---

## 3. `wp laca pull-db` — kéo database production về local

1. **Kiểm tra trước**: luật 0.4; chạy nhanh `sync-check` phần SSH; ước tính dung lượng DB production (sau khi loại bảng exclude) và chỗ trống local (cần ≥ 3× dung lượng).
2. **Sao lưu local**: `wp db export` → `<repo>/app/sql/backups/local-YYYYmmdd-His.sql.gz` (thư mục `app/sql/` đã gitignore — xác nhận). Giữ 5 bản mới nhất; bản cũ hơn chỉ liệt kê, KHÔNG tự xoá (luật không xoá file) — in lệnh để chủ dự án tự xoá.
3. **Xuất từ production (chỉ đọc)** qua SSH, stream thẳng về file local nén:
   `ssh <prod> "cd <path> && <wp> db export - --single-transaction --quick --default-character-set=utf8mb4 --exclude_tables=<…> | gzip -c" > app/sql/pulls/prod-YYYYmmdd-His.sql.gz`
   Bảng `exclude_data_only`: export riêng phần cấu trúc (`--no-data` cho các bảng đó) rồi nối vào file. Có thanh tiến độ theo số byte nhận được. Kiểm file gz toàn vẹn (`gzip -t`) trước khi import.
4. **Import vào local**: `wp db reset --yes` KHÔNG dùng; thay vào đó import đè (`gunzip -c … | wp db import -`) — các bảng production thay bảng local cùng tên; bảng chỉ có ở local giữ nguyên (in danh sách). Nếu prefix bảng production khác local → đọc prefix từ file dump, cập nhật cảnh báo + hướng dẫn (không tự sửa `wp-config.php`).
5. **Sau import — làm site local an toàn ("local-safe")** — BẮT BUỘC, tự chạy:
   - `wp search-replace <prod_url> <local_url> --all-tables-with-prefix --precise --skip-columns=guid` (cả biến thể `http/https`, `www`, dạng escape `\/`); serialized an toàn (WP-CLI xử lý).
   - **Ngắt kết nối hub/tracker**: xoá hoặc vô hiệu các option danh tính/khoá tracker (`_laca_tracker_endpoint`, `_laca_project_id`, `_laca_key_*`, `laca_key_*`, khoá Block Sync/Catalog, ThemeUpdater URL…) — dùng danh sách "Danh tính site" trong `ConfigRegistry` (plan Bộ cấu hình site), KHÔNG tự đặt danh sách mới. Đồng thời thêm vào `LacaDevTrackerClient` (và mọi chỗ gửi ra hub/remote-update) điều kiện: **không gửi gì khi `wp_get_environment_type()` là `local`/`development`** trừ khi hằng `LACA_TRACKER_ALLOW_LOCAL` = true (để dev tracker). Hiện tracker KHÔNG kiểm môi trường → site local dùng DB production sẽ báo cáo về hub dưới danh nghĩa production.
   - **Discord**: đặt `allow_non_production = false` trong `laca_discord_hub` sau khi kéo (option này đang có thể bật ở prod/hub); webhook production giải mã không được ở local (khác AUTH_KEY) nhưng vẫn tắt cho chắc.
   - Email: nếu môi trường local và không phải Local by Flywheel (Mailpit) → tắt SMTP thật (`_use_smtp` = no) để không gửi mail cho khách thật; luôn in cảnh báo.
   - Tắt các plugin chỉ dành cho production (danh sách trong `laca-sync.yml` → `local_deactivate_plugins`, mặc định: plugin cache trang, Wordfence, plugin backup) bằng `wp plugin deactivate` — chỉ trên local.
   - Huỷ toàn bộ cron của theme mang từ production rồi để theme tự lên lịch lại (tránh chạy lần lượt hàng loạt việc nặng); xoá transient; `wp rewrite flush`; tạo lại bản chụp theme options (`ThemeOptionsCache`) và `laca_storage_health`/audit cache.
   - Đặt option `laca_prod_url` = URL production (dùng cho mục 6) và `laca_last_pull` (thời gian, người chạy, dung lượng, file nguồn).
6. **Ẩn danh dữ liệu cá nhân** (`--anonymize` hoặc `db.anonymize: true`): email user không phải admin → `user{ID}@example.invalid`, xoá SĐT/địa chỉ trong usermeta phổ biến (billing_*/shipping_* nếu có WooCommerce), dữ liệu bài gửi form liên hệ (giữ số lượng, thay nội dung bằng chuỗi giả), email trong bảng log của theme. Có thể chạy lại riêng: `wp laca local-safe --anonymize`.
7. **Kết thúc**: in tóm tắt (bảng đã nhập, số dòng search-replace, plugin đã tắt, thời gian) và nhắc: đăng nhập local bằng tài khoản production; nếu cần, `wp user update <id> --user_pass=…`.
8. **Khôi phục**: `wp laca restore-db` import bản sao lưu local gần nhất (hoặc `--file`).

**DB lớn**: không giới hạn kích thước; stream + gzip; `--tables` để kéo một phần; timeout SSH `ServerAliveInterval=30`. Ghi rõ trong tài liệu: DB vài GB có thể mất vài phút–vài chục phút.

---

## 4. File lệch giữa production và git

### 4.1 `wp laca drift`
- `rsync -rlcn --itemize-changes --exclude-from=<tạm> <prod>:<path>/<p>/ <local>/<p>/` cho từng đường dẫn trong `files.paths` (chiều prod → local, `-n` dry-run, `-c` so checksum để không báo nhầm do mtime) → phân loại: **Prod có thêm**, **Prod đã sửa**, **Prod đã xoá** (chỉ có ở local).
- So phiên bản: `wp plugin list --format=json` + `wp theme list` + `wp core version` trên prod vs local → bảng "plugin X: prod 2.3.1 / local 2.2.0 (prod mới hơn)".
- Đánh dấu file local đang có thay đổi chưa commit (`git status --porcelain`) để tránh nhầm.
- Kết quả: bảng gọn + `--format=json` (dùng cho workflow mục 5). Mã thoát: 0 = không lệch, 3 = có lệch (để script khác dùng).

### 4.2 `wp laca pull-files`
- Từ chối chạy nếu các đường dẫn sẽ bị ghi có thay đổi local chưa commit (in danh sách; ép bằng `--force-dirty`).
- `rsync -rlc` prod → local cho `files.paths` (hoặc `--path`), exclude theo cấu hình. **Mặc định KHÔNG xoá** file chỉ có ở local; `--delete` mới xoá (và phải xác nhận thêm, liệt kê từng file trước) — vì là thao tác xoá, mặc định tắt.
- Sau khi chạy: in `git status` rút gọn + gợi ý commit message chuẩn Conventional Commits (vd `chore(plugins): sync production updates (woocommerce 9.4.1)`). **Không tự commit.**
- Theme cha `lacadev-client` dùng chung nhiều site: nếu file lệch nằm trong theme cha → cảnh báo "theme dùng chung, sửa ở bản gốc `~/Documents/wordpress/lacadev-client` rồi đồng bộ, đừng chỉ sửa ở site này".

---

## 5. GitHub Actions (mẫu trong `tools/sync-templates/.github/workflows/`)

### 5.1 `pull-production.yml` — "Kéo thay đổi từ production"
- Kích hoạt: `workflow_dispatch` (nút Run workflow) + `schedule` hằng ngày (mặc định tắt trong mẫu, bật bằng biến repo `SYNC_SCHEDULE=1`) + `repository_dispatch` loại `production-drift` (để FileWatch/hub gọi được về sau).
- Secrets: `PROD_SSH_HOST`, `PROD_SSH_PORT`, `PROD_SSH_USER`, `PROD_SSH_KEY` (private key riêng cho CI, chỉ đọc), `PROD_SSH_KNOWN_HOSTS`, biến `PROD_PATH`. Ghi trong tài liệu: tạo key riêng cho CI, thêm vào cPanel → SSH Access.
- Bước: checkout → thiết lập SSH (known_hosts cố định, không `StrictHostKeyChecking=no`) → `rsync` prod → workspace cho `themes` + `plugins` (exclude giống deploy) → nếu `git status` không đổi: kết thúc "Không có thay đổi" → có đổi: tạo nhánh `sync/production-YYYYmmdd-HHMM`, commit, mở PR (dùng `gh pr create` với `GITHUB_TOKEN`, không thêm action bên thứ ba) với nội dung: bảng plugin đổi phiên bản, danh sách file thêm/sửa/xoá, link run.
- Không bao giờ ghi lên server.

### 5.2 Chặn deploy đè — sửa mẫu `deploy.yml`
- Thêm bước **trước** bước FTP: SSH `rsync -rlcn --itemize-changes` (chỉ đọc) giữa production và commit TRƯỚC đó (`github.event.before` checkout tạm) cho các file sắp deploy. Nếu production có file khác commit trước (tức là có người sửa/cập nhật trên server mà chưa kéo về) và file đó nằm trong danh sách sắp deploy → **dừng deploy**, báo Discord: "Production có thay đổi chưa kéo về: … Chạy workflow 'Kéo thay đổi từ production' rồi merge trước khi deploy."
- Biến repo `DEPLOY_DRIFT_MODE` = `block` (mặc định) | `warn` | `off`. Thiếu secret SSH → bỏ qua bước (warn), không làm hỏng deploy hiện tại.
- Đưa thay đổi này vào **mẫu** + hướng dẫn áp dụng; với repo `lacadev-client` thì tạo bản đề xuất `deploy.yml.proposed` cạnh file cũ (KHÔNG sửa `deploy.yml` thật) để chủ dự án xem và tự thay.

---

## 6. Ảnh khi làm việc ở local — không cần tải `uploads/`

### 6.1 `LocalMediaProxy` (theme, `app/src/Features/Dev/LocalMediaProxy.php`)
- Chỉ bật khi: môi trường `local`/`development` VÀ có `laca_prod_url` (do `pull-db` đặt) hoặc hằng `LACA_PROD_URL`, VÀ `media.proxy` không tắt (option `laca_local_media_proxy`).
- Filter `wp_get_attachment_url`, `wp_get_attachment_image_src`, `wp_calculate_image_srcset`, `the_content`/`render_block` (thẻ `img`/`source` trỏ tới `/wp-content/uploads/`): nếu file KHÔNG tồn tại ở local → đổi host sang `laca_prod_url`. Cache kết quả `file_exists` trong request (mảng static) để không stat lặp.
- Không bao giờ chạy ở production (kiểm môi trường + so `home_url()` khác `laca_prod_url`).
- Thanh admin bar (chỉ local): nhãn nhỏ "Ảnh từ production" để biết đang proxy.

### 6.2 Upload mới ở local vẫn lưu local bình thường (không đụng).

### 6.3 `wp laca pull-media --since=30d` (tuỳ chọn): rsync `uploads/YYYY/MM` gần đây cho trường hợp cần làm việc offline.

---

## 7. Nút bấm & khởi tạo cho từng repo

- `wp laca sync-init`: chép từ `tools/sync-templates/` vào repo root (không ghi đè, in diff nếu khác): `laca-sync.example.yml`, `.vscode/tasks.json`, `.github/workflows/pull-production.yml`, `deploy.yml.proposed`; thêm vào `.gitignore` (append, không sửa dòng cũ): `laca-sync.yml`, `app/sql/pulls/`, `app/sql/backups/`.
- `.vscode/tasks.json` — các "nút" trong VS Code (Terminal → Run Task, hoặc gán phím tắt): "Laca: Kiểm tra kết nối production", "Laca: Kéo DB từ production", "Laca: Kéo DB (ẩn danh)", "Laca: Xem file lệch production", "Laca: Kéo file lệch production", "Laca: Khôi phục DB local". Mỗi task chạy WP-CLI trong đúng môi trường của Local (nạp shell của site — tìm cách Local by Flywheel cung cấp: script "site shell" trong `~/Library/Application Support/Local/…`; phát hiện tự động trong `sync-init`, không đúng thì in hướng dẫn chỉnh 1 dòng).
- Trang **Laca Admin → Hệ thống**, chỉ ở môi trường local: card "Đồng bộ với production" (chỉ đọc): trạng thái cấu hình `laca-sync.yml` (có/không — không hiện host/user), lần kéo DB gần nhất (`laca_last_pull`), proxy ảnh đang bật/tắt, các lệnh/VS Code task tương ứng (nút "Sao chép lệnh"). **Không chạy SSH từ trình duyệt** (tránh để web server cầm khoá SSH).

---

## 8. Chế độ khoá sửa file trên production (site do chủ dự án quản lý)

- Tài liệu + kiểm tra, không tự sửa `wp-config.php`:
  - Site do chủ dự án quản lý: khuyên thêm `define('DISALLOW_FILE_MODS', true);` (chặn cài/cập nhật/sửa plugin-theme trong wp-admin; mọi cập nhật làm ở local → commit → deploy) — nêu đánh đổi: core không tự vá bảo mật, phải cập nhật định kỳ.
  - Site khách tự quản lý: giữ cho cập nhật; dựa vào FileWatch (báo Discord) + workflow 5.1 + chặn deploy 5.2.
- `SecurityAudit`: thêm mục "Chế độ quản lý file" hiện trạng `DISALLOW_FILE_MODS`/`DISALLOW_FILE_EDIT` (thông tin, không trừ điểm khi tắt `DISALLOW_FILE_MODS` vì phụ thuộc mô hình khách).
- Thông báo Discord của FileWatch khi phát hiện lệch: thêm 1 dòng gợi ý "Chạy workflow 'Kéo thay đổi từ production' để tạo PR" (link `https://github.com/<repo>/actions/workflows/pull-production.yml` nếu biết repo qua biến cấu hình; không biết thì bỏ link).

---

## 9. Kiểm thử (KHÔNG phá dữ liệu thật)

**Cần chủ dự án chuẩn bị trước** (Antigravity hỏi qua agent-bridge nếu chưa có, KHÔNG tự tạo site):
- 1 site Local trống tên `laca-sync-test` (WordPress mới, kích hoạt `lacadev-client` + child) — nơi DUY NHẤT được import DB.
- `laca-sync.yml` trỏ tới production `clients.lacadev.com` qua SSH (chủ dự án tự điền host/user).

Các bước:
1. `php -l`, parity A/B, `wp i18n make-pot`, build nếu có asset.
2. `sync-check` với production thật → bảng OK.
3. `drift` với production thật (chỉ đọc) → ghi kết quả.
4. **Giả lập production bằng site local**: alias/`laca-sync.yml` riêng của `laca-sync-test` trỏ `ssh` tới `localhost` hoặc chế độ `path:` cục bộ tới `lacadev-client` (chỉ ĐỌC từ đó) → `pull-db` vào `laca-sync-test`: kiểm search-replace, local-safe (tracker/Discord bị vô hiệu — kiểm bằng cách gọi hàm gửi của tracker ở `laca-sync-test` và thấy không có request ra ngoài, dùng filter `pre_http_request` để bắt), ẩn danh, `restore-db` về đúng như trước (so dump cấu trúc + số dòng mỗi bảng).
5. `pull-db` từ production thật vào `laca-sync-test` — **chỉ khi chủ dự án đồng ý qua agent-bridge** (dữ liệu khách về máy dev); dùng `--anonymize`.
6. `pull-files` vào `laca-sync-test` từ nguồn giả lập (sửa 1 file plugin ở nguồn giả → drift báo → pull-files kéo về → git status hiện đúng; `--delete` liệt kê trước khi xoá; từ chối khi có thay đổi chưa commit).
7. `LocalMediaProxy`: ở `laca-sync-test` xoá… KHÔNG xoá file — dùng 1 attachment trỏ tới file không tồn tại local → URL đổi sang production; file tồn tại → giữ local; ở môi trường production giả (`WP_ENVIRONMENT_TYPE=production` tạm qua `-d auto_prepend_file`) → không đổi.
8. Workflow mẫu: `actionlint` nếu có sẵn (không cài thì kiểm YAML bằng parser) + chạy bước rsync/drift của workflow bằng script cục bộ giả lập.
9. Hồi quy: mở toàn bộ trang admin của `lacadev` + `lacadev-client` → không Fatal/Warning mới; `LacaDevTrackerClient` ở production (giả lập env production) vẫn gửi bình thường.

## 10. Tài liệu & báo cáo

- `doc/PROD-LOCAL-SYNC-GUIDE.md` (cả 2 theme): thiết lập SSH key (máy dev + key riêng cho CI), điền `laca-sync.yml`, chạy `sync-init`, dùng các nút VS Code, quy trình cho 2 loại khách (tự quản lý / chủ dự án quản lý), xử lý sự cố (wp-cli không có trên server, prefix bảng khác, DB lớn, hết dung lượng).
- Báo cáo `/tmp/laca-parity/sync/REPORT.md`: file tạo/sửa; output thật từng bước test; thời gian kéo DB (dung lượng/giây); chỗ lệch plan + lý do; danh sách việc chủ dự án phải tự làm (tạo site test, SSH key, secrets GitHub, thay `deploy.yml` bằng bản đề xuất). Progress qua agent-bridge sau mỗi mục 2→8 và khi xong.
