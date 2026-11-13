# PLAN — Gia cố bảo mật kiểu Wordfence + dọn nợ kỹ thuật (11/2026)

> Viết plan: Claude Code · Làm: Antigravity · Review: Claude Code
> Tiếp nối `PLAN-SECURITY-FEATURES-AND-ADMIN-PARITY-2026-11.md` và `PLAN-ADMIN-SETTINGS-UI-2026-11.md`.
> Áp dụng CẢ 2 theme; file dùng chung phải GIỐNG HỆT nhau:
> - A (hub): `/Users/lacadev/Documents/wordpress/lacadev/app/public/wp-content/themes/lacadev`
> - B (client, bản gốc): `/Users/lacadev/Documents/wordpress/lacadev-client/app/public/wp-content/themes/lacadev-client`
> Làm ở B trước, copy sang A, `cmp` từng file.

---

## 0. Luật bắt buộc

1. Chỉ sửa trong 2 theme (và build `lacadev-client-child`). Không đụng WP core, plugin, `wp-config.php`, `.htaccess`, `uploads/`.
2. Không xoá file, không git commit/push, không `composer require/update`, không cài package. (`composer dump-autoload` được phép nếu cần.)
3. **Giữ nguyên mọi bản vá cũ** (danh sách ở mục 0.3 của `PLAN-ADMIN-SETTINGS-UI-2026-11.md`, cộng thêm: class `laca-config-tab-content` trong `ConfigPage.php`). Đọc file trước khi sửa, sửa tối thiểu.
4. Code chạy lúc nạp theme KHÔNG gọi `carbon_get_*` (đọc `get_option('_ten_field')`).
5. **i18n**: mọi chữ hiển thị (kể cả nội dung tin Discord mới, thông báo lỗi đăng nhập, admin notice) qua `__()/esc_html__()` domain `laca`, msgid tiếng Việt, câu có biến dùng `sprintf` + `/* translators: */`. **KHÔNG dùng `I18n::t('vi', 'en')` của DiscordHub cho code mới** (mẫu ghi cứng 2 ngôn ngữ bị cấm theo quy ước dự án). Không ghi cứng chữ trong JS.
6. Bảo mật: AJAX mới có nonce + `current_user_can('manage_options')`; sanitize vào, escape ra; IP kiểm `FILTER_VALIDATE_IP`.
7. **Không bao giờ được làm admin bị khoá ngoài site**: mọi cơ chế chặn mới phải tôn trọng whitelist IP của LoginGuard, phải có công tắc tắt, và phải có lối thoát bằng hằng số trong `wp-config.php` (mục 5.3).
8. Request ra ngoài (HIBP, kiểm file công khai): đặt `user-agent` chung `WordPress; laca-theme` (KHÔNG gửi URL site, email), `timeout` ≤ 5s, lỗi mạng ⇒ bỏ qua (fail-open), không làm chậm/chặn đăng nhập.
9. UI dùng lại component `laca-*` + token `--laca-*`; CSS mới (nếu có) chỉ trong `resources/styles/admin/_settings-screens.scss`.
10. **Test giao diện phải kiểm phần tử HIỂN THỊ thật** (Playwright `isVisible()`/`toBeVisible()`), không chỉ kiểm có trong HTML. (Lỗi trang Cấu hình trống lần trước lọt qua vì test chỉ đọc HTML.)

---

## 1. Hiệu năng — `ContactFormTable::install()` chạy `dbDelta` mỗi request

**Hiện trạng (đã xác nhận):** `lacadev-client/theme/functions.php` (dòng ~553) gọi không điều kiện `ContactFormTable::install()`, `EmailLogTable::install()`, `LoginLogTable::install()` ở MỌI request. `EmailLogTable`/`LoginLogTable` đã có kiểm version ⇒ rẻ. `ContactFormTable::install()` KHÔNG kiểm version ⇒ `dbDelta` 2 bảng (nhiều query DESCRIBE/SHOW INDEX) mỗi lượt tải trang.

**Làm** (`app/src/Databases/ContactFormTable.php`, file dùng chung):
- Đầu `install()`: nếu `get_option(self::VERSION_KEY) === self::TABLE_VERSION` thì `return`, TRỪ khi đang ở chế độ tự chữa (dưới).
- Tự chữa trường hợp bảng bị xoá mà option còn (lý do code cũ bỏ kiểm version): chỉ khi `is_admin()` và transient `laca_cf_table_check` không tồn tại → chạy 1 query `SHOW TABLES LIKE %s` (prepare, `$wpdb->esc_like`) cho cả 2 bảng; thiếu bảng nào thì chạy `dbDelta`; đặt transient 12 giờ.
- Thêm tham số `bool $force = false` cho `install()`; `after_switch_theme` và `laca_install_project_manager_tables()` (hooks.php) truyền `true` để luôn chạy dbDelta khi đổi theme/nâng schema. Giữ nguyên câu lệnh CREATE TABLE và `TABLE_VERSION = '1.2.0'`.
- KHÔNG sửa `theme/functions.php` (khác nhau giữa A/B); sau khi `install()` có cổng version thì lời gọi ở đó tự rẻ.

**Kiểm:** bật `SAVEQUERIES` tạm qua đoạn code test CLI (không sửa wp-config): đếm số query chứa `DESCRIBE`/`SHOW COLUMNS`/`SHOW INDEX` trên 1 request frontend trước/sau ⇒ sau = 0. Thử xoá option version trên site local → request admin tiếp theo tạo lại đúng (rồi trả option về).

---

## 2. Cảnh báo deprecated của thư viện `htmlburger/wpemerge-theme-core` (PHP 8.2+)

**Hiện trạng:** `vendor/htmlburger/wpemerge-theme-core/src/Theme/Theme.php` dòng 101 và 104 dùng `"partials/${partial}-${child}.php"` ⇒ log `PHP Deprecated: Using ${var} in strings` (24 dòng trong log hub). Thư viện 0.15.1 đã ngừng phát triển, không có bản mới để nâng. Theme A commit cả `vendor/`; theme B bỏ qua `vendor/` trong git nhưng `build-release.sh` và rsync đều copy `vendor/` từ máy local ⇒ sửa file local là có hiệu lực khi phát hành.

**Làm (cả 2 theme):**
1. Tạo `scripts/patch-vendor.php` (PHP CLI thuần, idempotent):
   - Danh sách vá cố định: file `vendor/htmlburger/wpemerge-theme-core/src/Theme/Theme.php`, thay `"partials/${partial}-${child}.php"` → `"partials/{$partial}-{$child}.php"` và `"partials/${partial}.php"` → `"partials/{$partial}.php"`.
   - Đã vá rồi thì in `already patched`; không tìm thấy chuỗi gốc lẫn chuỗi đã vá ⇒ in cảnh báo và thoát mã 1 (không ghi bừa).
   - Sau khi vá, quét toàn bộ `vendor/htmlburger/**/*.php` tìm mẫu `"${` trong chuỗi nháy kép và in danh sách còn sót (chỉ báo, không tự sửa).
2. `composer.json` (cả 2 theme): thêm vào `"scripts"` hai mục `"post-install-cmd": ["@php scripts/patch-vendor.php"]` và `"post-update-cmd": ["@php scripts/patch-vendor.php"]` (giữ nguyên các script cũ). KHÔNG chạy `composer install/update`; chỉ chạy `php scripts/patch-vendor.php` bằng PHP 8.3 của Local.
3. `build-release.sh` (theme nào có file này): thêm `"${BUILD_DIR}/scripts/patch-vendor.php"` vào danh sách `rm -rf` các file không đóng gói (KHÔNG xoá thư mục `scripts/` nếu nó chứa thứ khác — kiểm trước).
4. Không sửa file nào khác trong `vendor/`.

**Kiểm:** `php -l vendor/htmlburger/wpemerge-theme-core/src/Theme/Theme.php` không còn dòng `Deprecated`; chạy lại script lần 2 in `already patched`; tải trang chủ + 1 trang có partial ở cả 2 site ⇒ `logs/php/error.log` không thêm dòng `Using ${var}`.

---

## 3. Lệch khoảng cách ở Bảo mật → tab "Đăng nhập"

File: `SecurityManager.php` (markup tab Đăng nhập) + `resources/styles/admin/_settings-screens.scss`.

1. Card "Giới hạn đăng nhập sai": nhãn **"IP luôn được phép"** đang dính sát ô "Thời gian khoá dài (giờ)" phía trên. Bọc textarea + nhãn + help + nút "Thêm IP của tôi" thành một `laca-field` riêng, có khoảng cách trên bằng đúng khoảng giữa các hàng field khác trong cùng card (dùng biến spacing có sẵn, không đặt số px mới nếu token có). Nút "Thêm IP của tôi" cách help text một nhịp nhỏ, không dính.
2. Card "Thông báo đăng nhập": nhãn trạng thái "Chưa cấu hình kênh Security trên Discord Hub" đang là badge chữ quá nhỏ/đậm khó đọc. Đổi thành 1 dòng `laca-alert` (biến thể warning khi chưa cấu hình, success khi đã cấu hình: "Đã kết nối kênh Security") cỡ chữ thân bài, link "Cấu hình Discord Hub →" nằm trong dòng đó. Trạng thái đọc từ `\App\Features\DiscordHub\Settings::channelStatus(Settings::CHANNEL_SECURITY)['state']` (`ok` / `broken` / `empty`; `broken` = cấu hình hỏng ⇒ biến thể danger "Webhook kênh Security bị lỗi, cần nhập lại").
3. So ảnh trước/sau ở 1440px và 375px.

---

## 4. Xung đột Heartbeat

`theme/setup/performance.php` (~dòng 63) luôn ép `heartbeat_settings['interval'] = 120` cho toàn site, trong khi trang Tối ưu mới có ô "Heartbeat" (`_heartbeat_mode`: `''` / `slow` / `editor_only`) do `Optimize::applyHeartbeatControl()` xử lý.

**Làm:** xác định thứ tự chạy 2 filter. Quy tắc mong muốn: khi `_heartbeat_mode === ''` (Mặc định) giữ hành vi hiện tại (120 giây); khi admin chọn `slow`/`editor_only` thì cài đặt của admin thắng. Thực hiện bằng cách cho filter trong `performance.php` bỏ qua khi `get_option('_heartbeat_mode')` khác rỗng (hoặc chuyển logic 120s vào nhánh mặc định của `applyHeartbeatControl()` — chọn 1 cách, ghi lý do). `performance.php` có thể khác nhau giữa A/B: sửa cùng một kiểu ở cả hai.

**Kiểm:** CLI `apply_filters('heartbeat_settings', [])` với 3 giá trị option ⇒ ra đúng interval/hành vi; trả option về rỗng.

---

## 5. Gia cố đăng nhập kiểu Wordfence

Thêm 3 class mới trong `app/src/Settings/Security/` (dùng chung A/B), đăng ký trong `app/hooks.php` cạnh `SvgUploadGuard`/`LoginHoneypot` (cùng hook `after_setup_theme` priority 15, bọc `class_exists`):
- `LoginPolicy.php` — mục 5.1, 5.2, 5.4, 5.5, 5.6
- `BreachedPasswordGuard.php` — mục 5.7
- `AdminLoginAlert.php` — mục 5.8

### 5.1 Option cấu hình chung

Option `laca_login_policy` (mảng, autoload yes, đọc qua `LoginPolicy::config()` + `wp_parse_args` với mặc định):

| Key | Kiểu | Mặc định | Ý nghĩa |
|---|---|---|---|
| `lock_invalid_usernames` | bool | `false` | Khoá IP ngay khi đăng nhập bằng username/email KHÔNG tồn tại |
| `banned_usernames` | string[] | `['admin','administrator','root','test','user','demo']` | Đăng nhập bằng tên này (khi KHÔNG tồn tại user thật trùng tên) ⇒ khoá IP ngay; cấm tạo user mới trùng tên |
| `require_2fa_roles` | string[] | `[]` | Vai trò bắt buộc bật 2FA (chọn từ `wp_roles()->get_names()`) |
| `require_2fa_grace_days` | int 0–30 | `7` | Số ngày ân hạn trước khi bắt buộc |
| `lostpw_max_per_hour` | int 0–100 | `5` | Số yêu cầu "Quên mật khẩu" tối đa / IP / giờ (0 = tắt giới hạn) |
| `disable_app_passwords` | bool | `false` | Tắt Application Passwords |
| `breached_check` | `'off'`\|`'admins'`\|`'all'` | `'admins'` | Kiểm mật khẩu bị lộ (5.7) |
| `new_ip_alert` | bool | `true` | Báo Discord khi admin đăng nhập từ IP mới (5.8) |

### 5.2 Khoá IP ngay lập tức (username không tồn tại / bị cấm)

- Thêm vào `LoginGuard` một hàm public tĩnh `lockNow(string $ip, string $username, string $reason): void` dùng ĐÚNG logic khoá hiện có (strike + `lockout`/`long_lockout`, transient `PREFIX.'l_'`, `addToLockedIndex`, `do_action('laca_login_guard_lockout', ...)`), bỏ qua nếu IP whitelist / `unknown` / đang bị khoá. Không nhân đôi code: tách phần khoá trong `onFailed()` ra private method dùng chung.
- `LoginPolicy` hook `wp_login_failed` priority **4** (trước LoginGuard priority 5): lấy `$username`; tồn tại = `username_exists()` hoặc (`is_email` && `email_exists()`).
  - Không tồn tại và (`lock_invalid_usernames` bật **hoặc** tên (lowercase, trim) nằm trong `banned_usernames`) ⇒ `LoginGuard::lockNow($ip, $username, 'invalid_username'|'banned_username')`.
  - Tên bị cấm nhưng TỒN TẠI user thật ⇒ không khoá ngay (để LoginGuard đếm bình thường) và ghi 1 cảnh báo vào SecurityAudit (mục 6) khuyên đổi username.
- Cấm tạo user mới trùng tên bị cấm: `validate_username` (filter, trả false nếu bị cấm) + `user_profile_update_errors` (khi `$update === false`) + `registration_errors` ⇒ lỗi "Tên đăng nhập này không được phép dùng."
- Không áp dụng cho request XML-RPC/REST? — Có áp dụng (wp_login_failed bắn chung), giữ nguyên.

### 5.3 Lối thoát khẩn cấp (bắt buộc)

Nếu hằng `LACA_SECURITY_BYPASS` được define `true` (admin tự thêm vào wp-config.php khi bị khoá), thì: `LoginGuard::blockLocked`, `LoginPolicy` (5.2, 5.4, 5.5), `BreachedPasswordGuard` chuyển hướng ép đổi mật khẩu, và honeypot đều **bỏ qua**; hiện admin notice đỏ "Đang bật chế độ vượt bảo mật LACA_SECURITY_BYPASS — xoá khỏi wp-config.php sau khi xử lý xong." Ghi hướng dẫn này vào help text card "Chính sách đăng nhập" và vào `doc/` (mục 9).

### 5.4 Bắt buộc 2FA theo vai trò

- Chỉ có hiệu lực khi `get_option('laca_2fa_master_enabled')` bật; nếu tắt mà `require_2fa_roles` không rỗng ⇒ UI hiện `laca-alert` warning "Cần bật 2FA tổng trước".
- User thuộc vai trò bắt buộc mà chưa `laca_2fa_enabled`+`laca_2fa_verified`:
  - Lần đầu phát hiện: lưu user meta `laca_2fa_enforce_since` = time().
  - Trong thời gian ân hạn: admin notice warning trên mọi trang admin "Bạn cần bật xác thực 2 bước trước ngày …" + link tới trang thiết lập 2FA của user.
  - Hết ân hạn: hook `admin_init` chuyển hướng mọi trang admin về trang thiết lập 2FA (đọc `TwoFactorAuth` để lấy đúng URL: profile section hoặc `admin.php?page=laca-2fa`), TRỪ: trang thiết lập đó, `profile.php`, `admin-ajax.php`, `admin-post.php`, đăng xuất, REST, cron, WP-CLI. Không chặn ở frontend.
- Không đụng luồng OTP hiện có của `TwoFactorAuth`.

### 5.5 Giới hạn "Quên mật khẩu"

- Hook `lostpassword_post` (tham số `WP_Error $errors`): đếm theo IP (transient `laca_lostpw_` + md5(ip), TTL 1 giờ). Vượt `lostpw_max_per_hour` ⇒ `$errors->add('laca_lostpw_limit', __('Bạn đã yêu cầu quá nhiều lần. Vui lòng thử lại sau.', 'laca'))`. Không tiết lộ email/username có tồn tại hay không. Bỏ qua IP whitelist.

### 5.6 Tắt Application Passwords

- `add_filter('wp_is_application_passwords_available', '__return_false')` khi bật.
- Trước khi giao: grep cả 2 theme + child xem có chỗ nào dùng Application Passwords (Basic auth tới REST của chính site, hub → client). Nếu có, ghi rõ trong báo cáo và để mặc định TẮT (đã là `false`).

### 5.7 Kiểm mật khẩu đã bị lộ — `BreachedPasswordGuard`

- API: `GET https://api.pwnedpasswords.com/range/{5 ký tự đầu SHA-1 viết HOA}` với header `Add-Padding: true`, `user-agent` theo mục 0.8, timeout 3s. So phần đuôi hash với các dòng trả về (bỏ dòng count 0 do padding). Lỗi/timeout ⇒ coi như "không rõ", KHÔNG chặn. Không lưu mật khẩu/hash vào DB, không log.
- Phạm vi theo `breached_check`: `admins` = user có `manage_options`; `all` = mọi user; `off` = tắt.
- Khi ĐẶT/ĐỔI mật khẩu: `user_profile_update_errors` (khi có `pass1`), `validate_password_reset` ⇒ nếu bị lộ: thêm lỗi "Mật khẩu này đã xuất hiện trong các vụ rò rỉ dữ liệu, hãy chọn mật khẩu khác." (chặn lưu).
- Khi ĐĂNG NHẬP: filter `check_password` (`$check, $password, $hash, $user_id`), chỉ khi `$check === true`, user thuộc phạm vi, và user meta `laca_hibp_checked_at` cũ hơn 24h. Bị lộ ⇒ đặt user meta `laca_password_breached` = time(); KHÔNG chặn đăng nhập.
- Có meta `laca_password_breached`: admin notice đỏ + `admin_init` chuyển hướng về `profile.php#password` (trừ chính profile.php, ajax, logout, các ngoại lệ như 5.4) cho tới khi đổi mật khẩu; hook `profile_update`/`after_password_reset` xoá meta khi mật khẩu đổi (so `user_pass` cũ/mới).

### 5.8 Báo admin đăng nhập từ IP mới — `AdminLoginAlert`

- Hook `wp_login` (priority 20). Chỉ user `manage_options`, chỉ khi `new_ip_alert` bật.
- User meta `laca_known_login_ips`: mảng `ip => last_seen`, giữ tối đa 10 IP gần nhất.
- Lần đầu (mảng rỗng) ⇒ chỉ ghi lại, không báo. IP chưa có ⇒ gửi `\App\Features\DiscordHub\Notifier::send(Settings::CHANNEL_SECURITY, 'login.new_ip', $embed)` với Embed gồm: username, IP, quốc gia (`Context::country()`), User-Agent, thời gian, link "Xem user". Chữ trong embed dùng `__()` (mục 0.5). Kênh chưa cấu hình ⇒ không làm gì (Notifier tự bỏ qua — kiểm lại).
- Không gửi trùng với cảnh báo "đăng nhập thành công từ IP đang brute-force" của LoginMonitor trong cùng request (nếu LoginMonitor đã gửi thì bỏ qua — dùng 1 cờ static hoặc kiểm transient brute-force giống LoginMonitor).

### 5.9 UI — card "Chính sách đăng nhập"

Bảo mật → tab Đăng nhập, đặt card mới **giữa "Chống bot" và "Thông báo đăng nhập"**. Nội dung theo đúng thứ tự:
1. Switch "Khoá IP ngay khi dùng tên đăng nhập không tồn tại" + help "Bảo vệ mạnh nhưng người gõ nhầm tên đăng nhập cũng bị khoá. Nên thêm IP văn phòng vào danh sách IP luôn được phép."
2. Textarea "Tên đăng nhập bị cấm" (mỗi dòng 1 tên) + help.
3. Checkbox nhóm "Bắt buộc 2FA cho vai trò" (danh sách vai trò) + số "Thời gian ân hạn (ngày)" + alert nếu 2FA tổng đang tắt.
4. Số "Giới hạn Quên mật khẩu (lần/giờ/IP)".
5. Switch "Tắt Application Passwords".
6. `laca-segmented` "Kiểm tra mật khẩu bị lộ": Tắt / Chỉ quản trị viên / Mọi người dùng + help nói rõ chỉ 5 ký tự đầu của mã băm được gửi đi.
7. Switch "Báo Discord khi quản trị viên đăng nhập từ IP mới".
8. Help cuối card: cách dùng `LACA_SECURITY_BYPASS` khi bị khoá.
- Nút "Lưu" → AJAX mới `laca_save_login_policy` (dùng `checkNonce()` sẵn có của SecurityManager), sanitize từng key theo bảng 5.1 (role phải thuộc `wp_roles()`; username qua `sanitize_user` + lowercase, bỏ trùng, tối đa 50 dòng). Trạng thái loading/success/error như các card khác.

---

## 6. Kiểm tra bảo mật (Bảo mật → tab "Kiểm tra bảo mật") — thêm mục

File: `app/src/Settings/Security/SecurityAudit.php`, dùng đúng closure `$add($cat, $id, $title, $status, $desc, $points, $fix)` sẵn có, cùng thang điểm.

1. **File nhạy cảm bị lộ** (`id: sensitive_files`):
   - Danh sách kiểm ở `ABSPATH`: `wp-config.php.bak`, `wp-config.bak`, `wp-config.old`, `wp-config.php~`, `wp-config.php.save`, `wp-config.php.swp`, `.wp-config.php.swp`, `wp-config.txt`, `.env`, `phpinfo.php`, `info.php`, `adminer.php`, `.git/HEAD`, mọi `*.sql`, `*.sql.gz`, `*.zip`, `*.tar.gz` ở đúng cấp `ABSPATH` (không đệ quy); ở `WP_CONTENT_DIR`: `debug.log`, `*.sql`.
   - File tồn tại ⇒ `wp_remote_head(home_url(<đường dẫn tương đối>))` (timeout 5s, `sslverify` false, `redirection` 0): HTTP 200 ⇒ status `fail` "Đang mở công khai"; khác ⇒ `warn` "Có trên server (chưa truy cập được từ ngoài)". Chỉ hiển thị TÊN file, tuyệt đối không đọc nội dung. Tối đa 20 file/lần.
   - Kết quả cache transient 1 giờ (`laca_audit_sensitive_files`), làm mới khi bấm chạy lại audit.
2. **Cập nhật còn chờ** (`id: pending_updates`): dùng `get_site_transient('update_core'|'update_plugins'|'update_themes')` (KHÔNG gọi `wp_update_plugins()` để tránh request chậm). Có bản core mới ⇒ `fail`; có plugin/theme cần cập nhật ⇒ `warn` kèm tên (tối đa 10); không có ⇒ `pass`.
3. **Username bị cấm đang tồn tại** (`id: banned_username_exists`): có user thật trùng `banned_usernames` (vd `admin`) ⇒ `warn`, fix: "Tạo admin mới với tên khác rồi xoá tài khoản này".
4. **2FA cho quản trị viên** (`id: admin_2fa`): đếm admin chưa bật 2FA; `require_2fa_roles` có `administrator` ⇒ pass.
5. **Application Passwords** (`id: app_passwords`): bật mà không có user nào tạo app password ⇒ `warn` khuyên tắt; tắt ⇒ pass.
6. **Kiểm mật khẩu bị lộ** (`id: breached_check`): `off` ⇒ warn.

---

## 7. Discord kênh Security

KHÔNG tự tạo/điền webhook (là secret, chủ dự án tự nhập). Chỉ làm phần UI ở mục 3.2 và đảm bảo nút "Gửi thử" bật khi `channelStatus(...)['state'] === 'ok'`. Ghi trong báo cáo: "Chờ chủ dự án nhập webhook tại Laca Admin → Discord Hub → kênh Security".

---

## 8. Đồng bộ, build, kiểm tra

1. B trước → copy A; cập nhật `/tmp/laca-parity/parity-check.sh` thêm mọi file mới/sửa (gồm `scripts/patch-vendor.php`, 3 class mới); chạy từ `/Users/lacadev/Documents/wordpress` ⇒ `Differing/Missing: 0`.
2. `php -l` mọi file PHP đã sửa/tạo (PHP 8.3 của Local), cả 2 theme.
3. Build: A `yarn build`; B `yarn build` trong `lacadev-client-child`. Không để watcher.
4. `wp i18n make-pot` cả 2 theme.
5. Test (cả 2 site, Playwright, **kiểm `isVisible`**), TRƯỚC KHI test thêm `127.0.0.1` và IP máy test vào whitelist LoginGuard để tránh tự khoá; test khoá bằng cách giả IP qua header mà `ClientIp` tin tưởng ở local, hoặc gọi trực tiếp `LoginGuard::lockNow()` bằng CLI:
   - 5.2: đăng nhập user không tồn tại khi bật `lock_invalid_usernames` ⇒ IP vào bảng "IP đang bị khoá"; tên `admin` (không tồn tại) ⇒ khoá ngay dù công tắc kia tắt; tạo user tên `admin` ở Users → Add New ⇒ bị từ chối. Mở khoá lại.
   - 5.3: define `LACA_SECURITY_BYPASS` bằng mu-plugin TẠM trong `/tmp` nạp qua `-d auto_prepend_file` ở CLI (KHÔNG sửa wp-config) ⇒ `blockLocked` bỏ qua.
   - 5.4: bật bắt buộc 2FA cho `administrator` với ân hạn 0 trên user test (tạo user test `laca_test_admin`, xoá meta sau) ⇒ bị chuyển về trang thiết lập 2FA; admin-ajax vẫn chạy. Tắt lại.
   - 5.5: 6 lần Quên mật khẩu ⇒ lần 6 báo lỗi giới hạn.
   - 5.6: bật ⇒ mục Application Passwords biến mất ở profile.
   - 5.7: đổi mật khẩu user test thành `password123` ⇒ bị chặn; mật khẩu ngẫu nhiên dài ⇒ qua. Giả lập mạng lỗi (filter `pre_http_request` trả WP_Error trong test) ⇒ không chặn.
   - 5.8: đăng nhập user test admin từ IP A rồi IP B ⇒ lần B gọi `Notifier::send` (bắt bằng filter/spy trong test nếu kênh chưa cấu hình).
   - 6: tạo tạm `wp-config.php.bak` RỖNG ở ABSPATH site local ⇒ audit báo; sau đó **chủ dự án tự xoá** file test (Antigravity không được xoá file — ghi tên file vào báo cáo) — HOẶC đặt tên file test đúng danh sách nhưng tạo trong ABSPATH rồi ghi rõ phải xoá. Ưu tiên: không tạo file, mà test bằng filter `laca_audit_sensitive_candidates` (thêm filter này vào code) trỏ tới 1 file tạm trong `/tmp`.
   - 1, 2, 3, 4: theo mục "Kiểm" từng phần.
6. Hồi quy: mở toàn bộ trang admin trong menu ở cả 2 site + frontend trang chủ, `wp-login.php` ⇒ không Fatal; `logs/php/error.log` không có Fatal/Warning mới; không còn dòng `Using ${var}`.
7. Trả MỌI option/meta/transient/user test về như trước (xoá user test `laca_test_admin` bằng `wp_delete_user` trong CLI là được phép vì do chính test tạo), whitelist bỏ IP đã thêm cho test.

## 9. Tài liệu

Thêm vào `doc/` cả 2 theme file `SECURITY-HARDENING-GUIDE.md` (ngắn, tiếng Việt): từng tính năng, nằm ở đâu, mặc định, rủi ro tự khoá, cách dùng `LACA_SECURITY_BYPASS`, cách nhập webhook Discord Security.

## 10. Báo cáo

`/tmp/laca-parity/hardening/REPORT.md`: danh sách file tạo/sửa (cả 2 theme), kết quả từng mục test (pass/fail + output thật), ảnh trước/sau mục 3 và card mục 5.9 (1440px + 375px) trong `/tmp/laca-parity/hardening/screenshots/`, chỗ lệch plan + lý do, danh sách việc chủ dự án phải tự làm. Gửi progress qua agent-bridge sau mỗi mục 1→6 và khi xong.
