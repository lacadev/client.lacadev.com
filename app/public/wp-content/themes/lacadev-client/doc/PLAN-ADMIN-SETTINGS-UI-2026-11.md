# PLAN — Màn hình cài đặt cho tính năng bảo mật & công cụ (11/2026)

> Người viết plan: Claude Code · Người làm: Antigravity · Review: Claude Code
> Tiếp nối `PLAN-SECURITY-FEATURES-AND-ADMIN-PARITY-2026-11.md` (backend đã xong, chưa có UI).
> Áp dụng cho CẢ 2 theme, file dùng chung phải GIỐNG HỆT nhau:
> - A (hub): `/Users/lacadev/Documents/wordpress/lacadev/app/public/wp-content/themes/lacadev`
> - B (client, bản gốc): `/Users/lacadev/Documents/wordpress/lacadev-client/app/public/wp-content/themes/lacadev-client`

---

## 0. Luật bắt buộc (đọc trước khi làm)

1. **Chỉ sửa trong 2 theme trên** (và build của `lacadev-client-child` cho B). Không đụng WP core, plugin, `uploads/`, DB (trừ option do chính tính năng ghi khi bấm Lưu lúc test).
2. **Không xoá file, không git commit/push**, không cài package mới.
3. **Không sửa đè các file Claude Code đã vá** trừ chỗ plan này yêu cầu, và giữ nguyên các bản vá đó:
   `app/helpers/responsive-images.php`, `app/src/Databases/ContactFormTable.php`, `app/src/Features/ContactForm/ContactFormManager.php`, `app/src/Settings/Security/{LoginHoneypot,SvgUploadGuard}.php`, `app/src/Settings/LacaTools/Management/ConfigChangeLog.php`, `app/src/Settings/LacaTools/{Optimize,Security}.php`, `app/src/Settings/AdminSettings.php` (block `class_exists` quanh "Đồng bộ Block"/"Site Tracker" và `encryptSmtpPasswordOnSave`).
4. **Code chạy lúc nạp theme KHÔNG được gọi `carbon_get_*`** trước `carbon_fields_fields_registered` (đã gây fatal khi tắt plugin). Đọc option CF bằng `get_option('_ten_field')`.
5. **i18n**: mọi chữ hiển thị qua `__()/esc_html__()/esc_attr__()` domain `laca`, msgid tiếng Việt. Không ghi cứng chữ trong JS — truyền qua `data-*` hoặc `wp_localize_script()`. Câu có biến dùng `sprintf` + `/* translators: */`.
6. **Bảo mật**: mọi AJAX mới `check_ajax_referer` + `current_user_can('manage_options')`; sanitize input; escape output; IP kiểm bằng `filter_var(..., FILTER_VALIDATE_IP)`.
7. **UI dùng lại 100% component có sẵn** ở `resources/styles/admin/components/` (`laca-card`, `laca-switch`, `laca-field`, `laca-field-row`, `laca-input`, `laca-select`, `laca-textarea`, `laca-segmented`, `laca-table`/`laca-table-wrap`, `laca-badge`, `laca-alert`, `laca-btn`, `laca-stat`, `laca-progress`, `laca-empty`, `laca-tabs`) và token `--laca-*` trong `_tokens.scss`. **Không thêm mã màu mới, không box-shadow cho card, không gradient.** CSS mới (nếu cần) đặt trong 1 file `resources/styles/admin/_settings-screens.scss`, import ở `resources/styles/admin/index.scss`.
8. **Không tự thêm section ngoài plan.** Mỗi màn chỉ có đúng các khối liệt kê dưới đây.
9. Mỗi nút gọi AJAX phải có đủ trạng thái: đang xử lý (disable + chữ "Đang …"), thành công (`laca-alert` success ngắn, tự ẩn sau 4s), lỗi (`laca-alert` danger, giữ lại). Danh sách rỗng dùng `laca-empty`.
10. Màn hẹp: bảng bọc `laca-table-wrap` (cuộn ngang trong khung, trang không cuộn ngang ở 782px và 375px). Field 2 cột về 1 cột dưới 782px.

---

## 1. Bảo mật → tab "Đăng nhập" (mở rộng tab đang có)

File: `app/src/Settings/Security/SecurityManager.php` (`renderLoginTab()`, `renderInlineScripts()`), `LoginGuard.php`, `LoginHoneypot.php`, `Features/DiscordHub/Monitors/LoginMonitor.php`.

Thứ tự khối từ trên xuống (giữ khối "Tùy chỉnh URL đăng nhập" hiện có, đặt ở vị trí 2):

1. **Card "IP đang bị khoá"** — đây là việc chính của tab, đặt đầu.
   - Bảng: IP (monospace) · Khoá đến (giờ:phút ngày/tháng theo múi giờ site) · Còn lại (vd "12 phút", "23 giờ") · nút `laca-btn` nhỏ "Mở khoá".
   - Nguồn: `LoginGuard::getLockedIps()`; mở khoá: AJAX có sẵn `laca_login_guard_unlock` (nonce `laca_login_guard_nonce`, field `_nonce`, `ip`). Mở khoá xong xoá dòng khỏi bảng, không reload.
   - Rỗng: `laca-empty` "Không có IP nào đang bị khoá."
   - Tiêu đề card có `laca-badge` số IP đang khoá (ẩn khi 0).
2. Card "Tùy chỉnh URL đăng nhập" (đang có, không đổi).
3. **Card "Giới hạn đăng nhập sai"** (lưu vào option `LoginGuard::SETTINGS_OPTION`, giây ↔ phút/giờ quy đổi ở server):
   - Số lần sai tối đa (số, 3–20, mặc định 5) · Trong khoảng (phút, mặc định 15) · Khoá trong (phút, mặc định 30) · Khoá dài sau N lần bị khoá (số, mặc định 3) · Thời gian khoá dài (giờ, mặc định 24).
   - "IP luôn được phép" — `laca-textarea`, mỗi dòng 1 IP; dưới ô có nút phụ "Thêm IP của tôi (x.x.x.x)" lấy IP qua `\App\Helpers\ClientIp::get()` nếu class có, nếu không `REMOTE_ADDR`. IP sai định dạng → không lưu dòng đó và báo danh sách dòng bị bỏ.
   - Nút "Lưu" → AJAX mới `laca_save_login_guard_settings`.
4. **Card "Chống bot"**: 1 `laca-switch` "Honeypot form đăng nhập" (option `laca_login_honeypot`, giá trị `'yes'`/`'no'`, mặc định bật) + 1 dòng giải thích. Lưu cùng nút ở card 3 hoặc nút riêng — chọn nút riêng, AJAX dùng chung `laca_save_login_guard_settings` với field `honeypot`.
5. **Card "Thông báo đăng nhập"**: dòng trạng thái (đã cấu hình Discord hay chưa — đọc đúng cách `LoginMonitor`/DiscordHub đang kiểm tra cấu hình) + nút "Gửi thử". AJAX mới `laca_login_notify_test` gửi 1 tin thử qua đúng kênh LoginMonitor đang dùng, nội dung ghi rõ "[Thử]". Chưa cấu hình → nút disable, kèm link tới trang cấu hình Discord.

## 2. Bảo mật → tab "Gia cố" (mở rộng)

File: `SecurityManager.php` (`renderHardeningTab()`), `SecurityHeaders.php`.

Thêm **1 card "HTTP Security Headers"** DƯỚI card đang có:
- Mỗi header 1 dòng: `laca-switch` + tên + 1 câu giải thích ngắn tiếng Việt: X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, CSP, Ẩn X-Powered-By.
- HSTS: switch + `laca-select` thời hạn (6 tháng / 1 năm / 2 năm → giây) + switch "Áp dụng cho subdomain" (`hsts_include_sub`); ghi chú "Chỉ gửi khi site chạy HTTPS".
- CSP: dưới switch có `laca-alert` warning: "Nếu video/iframe/script bên ngoài bị chặn, thêm domain qua filter `laca_csp_frame_src` hoặc tắt CSP."
- Nút "Lưu" → AJAX mới `laca_save_security_headers` → `SecurityHeaders::saveConfig()` (chỉ nhận đúng các key trong `defaults()`, ép kiểu bool/int).
- Nút phụ "Kiểm tra header" → AJAX mới `laca_check_security_headers`: `wp_remote_head(home_url('/'), ['timeout'=>10,'sslverify'=>false])`, trả về các header bảo mật đang gửi thật; hiển thị bảng Header · Giá trị (cắt 120 ký tự, có title đầy đủ) · `laca-badge` "Đang gửi"/"Không có". Lỗi request → `laca-alert` danger kèm thông báo lỗi.
- Mặc định PHẢI giữ đúng hành vi hiện tại (đã xác nhận header + CSP giống bản cũ từng ký tự). Không thêm nonce vào header CSP.

## 3. Laca Admin → "Dọn dẹp DB" (mở rộng)

File: `app/src/Settings/LacaTools/Management/DatabaseCleaner.php` (`renderPage()`, `handleScheduleSave()`).

Thêm **card "Dọn tự động"** đặt TRÊN danh sách dọn tay:
- `laca-switch` Bật/tắt (mặc định tắt) · `laca-segmented` Hằng ngày / Hằng tuần · checkbox các hạng mục (dùng đúng danh sách hạng mục mà dọn tay đang có) · "Giữ lại N revision mới nhất mỗi bài" (số, mặc định 5).
- Dòng trạng thái: "Lần chạy cuối: … — đã xoá X mục" (từ `LAST_RUN_OPTION`) hoặc "Chưa chạy lần nào"; "Lần chạy tới: …" (`wp_next_scheduled(CRON_HOOK)`) khi đang bật.
- Lưu qua AJAX có sẵn `laca_db_schedule` — đọc `handleScheduleSave()` để gửi đúng tên field; nếu thiếu field nào thì bổ sung ở handler, không đổi tên option.
- Nút "Chạy ngay" dùng lại luồng "Dọn tất cả" đang có (không thêm luồng mới).

## 4. Trang "Tối ưu" (Carbon Fields, AdminSettings.php)

Thêm 2 field vào container tối ưu đang có (cạnh các checkbox disable emoji/jQuery migrate):
- `Field::make('select', 'revision_limit', __('Số bản sửa (revision) giữ lại', 'laca'))` — options: `''` Mặc định WordPress, `0` Không lưu, `3`, `5`, `10`, `20`.
- `Field::make('select', 'heartbeat_mode', __('Heartbeat', 'laca'))` — `''` Mặc định, `slow` Chậm (60 giây), `editor_only` Chỉ trong trình soạn bài.
- Kiểm tra `Optimize::applyRevisionLimit()` đọc `_revision_limit`: giá trị `''` phải = không can thiệp (hiện code mặc định `-1`; CF lưu chuỗi → ép int chỉ khi `!== ''`). Không gọi `carbon_get_*` ở đây.

## 5. Trang mới Laca Admin → "Cấu hình" (slug `laca-config`)

File mới: `app/src/Settings/LacaTools/Management/ConfigPage.php` (đăng ký submenu dưới `laca-admin`, cap `manage_options`), đăng ký trong `ManagementExperience.php` cạnh `ConfigExportImport`/`ConfigChangeLog`. Trang có 2 tab dạng `nav-tab` giống trang Bảo mật (`?tab=transfer` | `?tab=log`).

**Tab "Xuất / nhập"**
- Card "Xuất cấu hình": 1 câu giải thích ("Không gồm mật khẩu, key, token") + nút "Tải file cấu hình" → gọi `laca_config_export`, trình duyệt tải file `laca-config-{host}-{YYYYmmdd}.json`.
- Card "Nhập cấu hình":
  1. Chọn file `.json` (input file, giới hạn 1MB, đọc ở client).
  2. Nút "Xem trước" → gọi `laca_config_import` với `dry_run=1` (BỔ SUNG chế độ này vào `handleImport()`: không ghi gì, trả về danh sách `{option, status: new|changed|same|rejected, old, new}` đã tóm tắt ≤120 ký tự; option ngoài whitelist hoặc secret → `rejected`).
  3. Bảng xem trước: Option · Hiện tại · Sẽ thành · `laca-badge` (Mới / Khác / Giống / Bị bỏ qua). Mặc định ẩn các dòng "Giống" (có switch "Hiện dòng giống").
  4. Nút `laca-btn` danger "Áp dụng N thay đổi" + `confirm()` → gọi lại không có `dry_run`. Xong hiện số option đã ghi.

**Tab "Nhật ký thay đổi"**
- Bảng từ `ConfigChangeLog::getLog()`: Thời gian · Người đổi (display name, user 0 = "Hệ thống") · IP · Option · Cũ → Mới (monospace, cắt 80 ký tự, title đầy đủ).
- Ô tìm theo tên option (lọc phía server qua `?s=`), phân trang 20 dòng/trang (server-side, dùng `paginate_links`).
- Nút "Xoá nhật ký" (danger, confirm) → AJAX có sẵn `laca_config_changelog_clear` (nonce `laca_config_changelog`, field `_nonce`).
- Rỗng: `laca-empty` "Chưa có thay đổi nào được ghi lại."

## 6. Dashboard → widget "Lưu trữ & quyền file"

File: `app/src/Settings/LacaTools/Management/DashboardWidgets.php` (thêm widget id `lacadev_storage_health`, chỉ `manage_options`) + helper tính toán `app/src/Settings/LacaTools/Management/StorageHealth.php` (mới).

- 3 `laca-stat`: Database · Uploads · wp-content (tổng). Nếu `disk_free_space()` khả dụng (không bị disable_functions) thêm dòng "Ổ đĩa còn trống: …".
  - DB: `SUM(data_length+index_length)` từ `information_schema.TABLES` cho `DB_NAME` (prepare), chỉ bảng có `$wpdb->prefix`.
  - Thư mục: `RecursiveDirectoryIterator` (SKIP_DOTS), dừng khi quá 15 giây → hiển thị "≥ X (chưa quét hết)".
- Kết quả lưu transient `laca_storage_health` 12 giờ. **Không tính khi tải Dashboard**: chưa có cache → hiện "Chưa có số liệu" + nút "Tính ngay"; có cache → hiện số + "Cập nhật lúc …" + nút "Tính lại" (AJAX `laca_storage_health_refresh`).
- **Cảnh báo quyền file (chỉ cảnh báo, KHÔNG có nút chmod)**, danh sách `laca-badge` danger/warning:
  - `wp-config.php` cho nhóm/người khác ghi (perm & 0022) → danger; đọc được bởi mọi người (& 0004) → warning.
  - `wp-content`, `uploads`, thư mục theme có quyền 0777 → danger.
  - `uploads` không ghi được (`wp_is_writable`) → danger.
  - `.htaccess` (nếu có) ghi được bởi mọi người → warning.
  - Không có cảnh báo → dòng `laca-badge` success "Quyền file ổn".
- Link "Dọn dẹp DB →" tới trang ở mục 3.

---

## 7. Đồng bộ, build, kiểm tra

1. Làm ở B trước, copy sang A; mọi file dùng chung phải `cmp` giống nhau. Cập nhật `/tmp/laca-parity/parity-check.sh` thêm các file mới/đã sửa của plan này, chạy từ `/Users/lacadev/Documents/wordpress`.
2. `php -l` mọi file PHP đã sửa ở cả 2 theme (PHP 8.3 của Local).
3. Build: A `yarn build` trong theme A; B `yarn build` trong `lacadev-client-child`. Không để watcher chạy.
4. `wp i18n make-pot` cho cả 2 theme nếu có WP-CLI; không có thì ghi lại trong báo cáo.
5. Test trên trình duyệt (cả 2 site, đăng nhập admin), chụp ảnh từng màn ở 1440px và 375px vào `/tmp/laca-parity/ui/screenshots/`:
   - Tab Đăng nhập: khoá thử 1 IP giả (gọi `LoginGuard` qua sai mật khẩu bằng user không tồn tại 5 lần từ IP test, hoặc thêm tay vào index ở site local), thấy trong bảng, bấm Mở khoá; lưu ngưỡng, whitelist có 1 IP sai → báo bị bỏ; bật/tắt honeypot → xem field ẩn có/không trên wp-login.php; Gửi thử thông báo.
   - Tab Gia cố: tắt 1 header → Lưu → "Kiểm tra header" thấy mất header đó → bật lại.
   - Dọn dẹp DB: bật lịch → thấy "Lần chạy tới" → tắt lại (để mặc định tắt khi giao).
   - Tối ưu: chọn revision 5 → lưu → `get_option('_revision_limit')` = '5'; đặt lại Mặc định.
   - Cấu hình: Xuất → mở file thấy KHÔNG có `_smtp_password`/key; Nhập chính file đó → xem trước toàn "Giống"; sửa 1 giá trị trong file → thấy "Khác" → Áp dụng → nhật ký có dòng tương ứng.
   - Dashboard: widget "Chưa có số liệu" → Tính ngay → có số + cảnh báo quyền.
6. Chạy lại kiểm tra hồi quy: mở tất cả trang admin trong menu (Laca Admin, Bảo mật, Laca SEO, Contact Forms…) ở cả 2 site, không có "Fatal error"/"critical error"; `logs/php/error.log` của 2 site không có Fatal/Warning mới.
7. Trả mọi cài đặt đã đổi khi test về mặc định.

## 8. Báo cáo

Ghi `/tmp/laca-parity/ui/REPORT-UI.md`: danh sách file đã sửa/tạo (cả 2 theme), kết quả mục 7 (pass/fail từng dòng, output thật), ảnh chụp, chỗ nào lệch plan và lý do. Gửi progress qua agent-bridge khi xong từng màn (1→6) và khi hoàn tất.
