# PLAN — Bảng điều khiển Laca Admin, Thông số hệ thống, Quản lý dung lượng, làm rõ Tối ưu (11/2026)

> Viết plan: Claude Code · Làm: Antigravity · Review: Claude Code
> Mẫu tham khảo: ảnh chụp Sitevorx Pro do chủ dự án gửi (dashboard, thông số máy chủ, quản lý dung lượng, tối ưu nâng cao). Code tham khảo hành vi (CHỈ ĐỌC, không chép nguyên code): `/Users/lacadev/Documents/wordpress/lacadev/app/public/wp-content/plugins/Sitevorx-Pro`.
> Áp dụng CẢ 2 theme, file dùng chung GIỐNG HỆT nhau. Làm ở B trước rồi copy sang A:
> - A (hub): `/Users/lacadev/Documents/wordpress/lacadev/app/public/wp-content/themes/lacadev`
> - B (client): `/Users/lacadev/Documents/wordpress/lacadev-client/app/public/wp-content/themes/lacadev-client`

---

## 0. Luật bắt buộc

1. Giữ nguyên toàn bộ luật mục 0 của `PLAN-HARDENING-WORDFENCE-STYLE-2026-11.md` (phạm vi sửa, không xoá file, không commit, không cài package, giữ bản vá cũ, không `carbon_get_*` lúc nạp theme, i18n `__()` domain `laca` msgid tiếng Việt, nonce + `manage_options` cho mọi AJAX, request ra ngoài fail-open).
2. **Giao diện**: dùng component `laca-*` + token `--laca-*` có sẵn (`resources/styles/admin/components/`, `_tokens.scss`). Phong cách **flat** như phần còn lại của Laca Admin: nền xám nhạt, card trắng viền 1px, không bóng đổ cho card, không gradient, không glow. Khối "hero" của dashboard dùng nền tối của chrome admin (`--laca-chrome-bg` / `--laca-chrome-fg`), KHÔNG chép nền gradient tím/xanh của Sitevorx. CSS mới gom vào 1 file `resources/styles/admin/_laca-dashboard.scss` (import ở `resources/styles/admin/index.scss`). Không icon font mới: dùng Dashicons hoặc Font Awesome đang được admin nạp (kiểm tra cái nào đang có).
3. **Hiệu năng**: mở trang dashboard KHÔNG được chạy việc nặng (quét thư mục, audit có request HTTP, gọi API cập nhật). Chỉ đọc dữ liệu đã cache (transient/option). Việc nặng chạy qua nút + AJAX, có trạng thái đang tải.
4. **Không làm** các phần Sitevorx thuộc phạm vi plugin/nhà cung cấp: Premium/MyThemeShop/Rank Math, "Chỉ hosting iNET", Di chuyển website/backup, đổi ngôn ngữ VI/EN bằng nút (ngôn ngữ đi theo locale WP).
5. Mỗi trạng thái phải có hình hiển thị: đang tải, rỗng/chưa có số liệu, lỗi, bình thường. Kiểm thử UI bằng Playwright **`isVisible()`** + chụp ảnh 1440px và 375px.
6. Không trang nào cuộn ngang ở 782px và 375px; lưới card về 1 cột dưới 782px.

---

## 1. Bảng điều khiển "Tổng quan" — trang chủ của Laca Admin

### 1.1 Menu

- Tạo trang mới slug `laca-dashboard`, tiêu đề "Tổng quan", class mới `app/src/Settings/LacaAdmin/LacaDashboard.php` (đăng ký ở nơi các trang Laca Admin khác được đăng ký — đọc `AdminUxService`/`AdminSettings`/`LacaAdminMenuOrganizer` để đặt đúng chỗ), cap `manage_options`.
- Bấm "Laca Admin" ở sidebar WP phải mở "Tổng quan". Cách làm: đặt `laca-dashboard` là submenu ĐẦU TIÊN của `laca-admin` và cho mục menu cha trỏ tới nó (sửa `$submenu`/`$menu` ở `admin_menu` priority muộn, hoặc cách WP chuẩn tương đương). **Không đổi slug `laca-admin`** — trang "Cấu hình chung" vẫn ở `admin.php?page=laca-admin` (rất nhiều link đang trỏ tới).
- Laca Admin Dock (`LacaAdminMenuOrganizer`): thêm "Tổng quan" lên đầu nhóm "Tổng quan"; trang "Hệ thống" (mục 2) vào nhóm "Hệ thống".

### 1.2 Bố cục (bám ảnh mẫu, từ trên xuống)

**A. Hero (card nền tối, full width)**
- Trái: dòng nhỏ "Xin chào, {display_name}"; tiêu đề trạng thái theo điểm sức khoẻ: ≥ 80 "Website đang ổn định", 50–79 "Website cần chú ý", < 50 "Website cần xử lý ngay"; 1 câu phụ: "Còn N việc nên làm để website an toàn và chạy nhanh hơn." (N = số việc ở khối C; N = 0 → "Không còn việc nào cần xử lý."); nút sáng "Xem việc nên làm ↓" (cuộn tới khối C) + nút viền "Kiểm tra lại" (AJAX chạy lại audit, mục 1.3).
- Phải: vòng tròn điểm sức khoẻ (SVG `<circle>` stroke-dasharray, màu theo mức: success/warning/danger token), số điểm to ở giữa, nhãn "Điểm sức khoẻ", badge mức ("Tốt" / "Cần chú ý" / "Nguy hiểm"). Có `aria-label` "Điểm sức khoẻ X trên 100".
- Dưới cùng trong hero: 4 thẻ KPI ngang (về 2×2 ở tablet, 1 cột ở mobile):
  1. **Lớp bảo mật** — "X/Y" + thanh Y vạch (vạch bật = sáng) + nhãn mức (Yếu < 40%, Trung bình < 75%, Tốt ≥ 75%). Y = danh sách cố định các lớp: Giới hạn đăng nhập sai (luôn bật nếu LoginGuard chạy), Honeypot, 2FA tổng, URL đăng nhập tuỳ chỉnh, Security headers (ít nhất CSP + X-Frame), Chặn dò username, Tắt XML-RPC, Lọc SVG khi upload (luôn bật), Kiểm tra mật khẩu bị lộ (≠ off). Đọc trạng thái từ đúng option/class hiện có — liệt kê nguồn từng lớp trong báo cáo.
  2. **Gửi email** — "Đã cấu hình SMTP" / "Chưa cấu hình" (`get_option('_use_smtp') === 'yes'` và có host + username) + dòng phụ host.
  3. **Dọn dẹp tự động** — "Đang bật · hằng ngày/tuần" / "Đang tắt" (từ `DatabaseCleaner::SCHEDULE_OPTION`) + dòng phụ "Lần chạy tới …" hoặc "Chưa có lịch chạy tự động".
  4. **Cập nhật** — "N bản cập nhật" / "Đã cập nhật đủ" (đếm từ `get_site_transient('update_core'|'update_plugins'|'update_themes')`, KHÔNG gọi API) + dòng phụ "Core / plugin / theme".
  Mỗi thẻ là link tới trang xử lý tương ứng.

**B. Hàng 2 cột (≈ 2/3 + 1/3, về 1 cột < 1024px)**
- **C. "Việc nên làm"** (card trái, badge số lượng):
  - Nguồn: kết quả `SecurityAudit::run()` ĐÃ CACHE (xem 1.3) — lấy mục `fail` trước rồi `warn`, tối đa 6; cộng 2 việc không thuộc audit: "Dọn dẹp tự động đang tắt" (nếu tắt), "Chưa cấu hình gửi email SMTP" (nếu chưa).
  - Mỗi dòng: ô icon màu theo mức (danger/warning, dùng token) · tiêu đề ngắn · nút "Xử lý" dẫn tới trang/tab sửa (dùng `fix`/id của audit để map URL; không map được thì tới `admin.php?page=laca-security&tab=audit`).
  - Rỗng: `laca-empty` "Không còn việc nào cần làm. Website đang ở trạng thái tốt."
  - Chưa có audit cache: dòng "Chưa có kết quả kiểm tra" + nút "Kiểm tra ngay".
- **D. "Dung lượng website"** (card phải, link "Chi tiết →" tới tab Dung lượng mục 3):
  - Số tổng to (wp-content + DB) + thanh xếp chồng 3 màu (mã nguồn = plugins+themes+khác, media = uploads, database) + 3 dòng chú thích có số.
  - Nguồn: cache của `StorageHealth` (transient `laca_storage_health`) — MỞ RỘNG `StorageHealth` để lưu thêm tách nhỏ plugins/themes/uploads/khác (mục 3.2). Chưa có cache → "Chưa có số liệu" + nút "Tính ngay" (AJAX `laca_storage_health_refresh` có sẵn).

**E. "Chức năng"** — lưới card 3 cột (2 cột tablet, 1 cột mobile), mỗi card: ô icon · tiêu đề · 1–2 dòng mô tả · link "Mở →". Toàn bộ card bấm được (link bao card hoặc stretched link, focus rõ). Danh sách (chỉ những trang ĐANG có trong theme; trang nào không tồn tại trên site đó thì ẩn card):
  1. Bảo mật — `laca-security` — "Khoá IP, URL đăng nhập riêng, 2FA, honeypot, security headers, quét mã độc."
  2. Tối ưu tốc độ — `laca-tools` — "Tải ảnh chậm, giảm Heartbeat, gỡ Emoji/oEmbed/jQuery Migrate, giới hạn revision."
  3. Dọn dẹp Database — `laca-db-cleaner` — "Dọn revision, bản nháp tự động, thùng rác, spam và lịch dọn tự động."
  4. Hệ thống & dung lượng — `laca-system` (mục 2–3) — "Thông số máy chủ, giới hạn PHP, dung lượng từng phần, file lớn."
  5. Gửi mail SMTP & nhật ký email — trang SMTP/Email Log hiện có — "Cấu hình SMTP và xem email đã gửi."
  6. Sao lưu cấu hình — `laca-config` — "Xuất/nhập cấu hình giữa các website và xem nhật ký thay đổi."
  7. Form liên hệ — `laca-contact-forms` — "Tạo form, xem liên hệ khách gửi."
  8. Laca SEO — `laca-seo` — "Tối ưu SEO, sitemap, chuyển hướng."
  9. Bảo trì website — trang/công tắc Maintenance hiện có — "Bật chế độ bảo trì, giới hạn IP xem site."

**F. "Hosting"** — hàng 6 ô nhỏ (3×2 tablet, 2×3 mobile), mỗi ô: icon · nhãn chữ hoa nhỏ · giá trị: PHP (`PHP_VERSION`), WordPress (`get_bloginfo('version')`), Memory limit (`WP_MEMORY_LIMIT` và `ini_get('memory_limit')`, lấy cái hiệu lực), Upload tối đa (`size_format(wp_max_upload_size())`), SSL/HTTPS (`is_ssl()` hoặc `home_url` bắt đầu https → "Đã bật" success, ngược lại "Chưa bật" danger), WP_DEBUG ("Tắt" success / "Bật" warning). Link "Xem chi tiết →" tới mục 2.

### 1.3 Điểm sức khoẻ & cache audit

- Thêm vào `SecurityAudit` hàm `getCached(): ?array` đọc transient `laca_security_audit_result` (24h) và để `run()` ghi transient đó mỗi lần chạy (giữ API cũ). Dashboard chỉ đọc cache.
- Điểm sức khoẻ = `round(score / max * 100)` của audit cache; chưa có cache → vòng tròn hiện "—" + "Chưa kiểm tra".
- Nút "Kiểm tra lại" → AJAX `laca_dashboard_refresh_audit` (nonce, cap) chạy `SecurityAudit::run()`, trả HTML khối A + C mới hoặc dữ liệu để JS vẽ lại; trạng thái đang tải ("Đang kiểm tra…") và lỗi.

---

## 2. Trang "Hệ thống" — tab "Thông số máy chủ"

Trang mới slug `laca-system`, class `app/src/Settings/LacaTools/Management/SystemInfoPage.php`, 2 tab `nav-tab` như trang Cấu hình: `?tab=server` (mặc định) | `?tab=storage` (mục 3).

Tab "Thông số máy chủ" (bám ảnh mẫu), các card:
1. **Môi trường máy chủ** (bảng 2 cột nhãn/giá trị): Web server (`$_SERVER['SERVER_SOFTWARE']`, monospace), Phiên bản PHP (màu success nếu ≥ 8.1, warning 7.4–8.0, danger < 7.4), MySQL/MariaDB (`$wpdb->db_server_info()` hoặc `SELECT VERSION()`), Dung lượng database (từ cache StorageHealth, chưa có → "Chưa tính"), Múi giờ site (`wp_timezone_string()`), WP-Cron (`DISABLE_WP_CRON` → "Cron thật của hosting" / "Pseudo-cron"), HTTPS.
2. **Giới hạn PHP** (lưới 2 cột ô nhỏ): Memory limit, Max execution time, Max input vars, Upload / Post max size, `max_input_time`, OPcache (bật/tắt).
3. **WordPress**: phiên bản, WP_DEBUG, WP_DEBUG_LOG, DISALLOW_FILE_EDIT, WP_MEMORY_LIMIT / WP_MAX_MEMORY_LIMIT, số plugin đang bật, theme đang dùng + phiên bản, multisite có/không.
4. **PHP Extensions**: chip cho `get_loaded_extensions()` sắp xếp A–Z; các extension quan trọng cho WP (`curl, gd, imagick, mbstring, mysqli, openssl, zip, intl, exif, fileinfo, sodium, xml`) được đánh dấu ✓ màu success; extension quan trọng mà THIẾU hiện ở đầu card dạng `laca-alert` warning "Thiếu: …".
5. Nút "Sao chép thông tin hệ thống" (copy text thuần các mục 1–3 vào clipboard để gửi kỹ thuật viên; không gồm đường dẫn tuyệt đối máy chủ, không gồm secret). Trạng thái "Đã sao chép".

Chỉ đọc; không có thao tác thay đổi gì.

---

## 3. Trang "Hệ thống" — tab "Dung lượng"

### 3.1 Bố cục (bám ảnh mẫu)
Tab con dạng `laca-tabs`/segmented: "Dung lượng chi tiết" | "File cỡ lớn (> 50 MB)".

**Dung lượng chi tiết**
- Card "Tổng quan dung lượng" + nút "Làm mới" (AJAX tính lại): 3 `laca-stat` WP-content · Database · Tổng cộng.
- "Chi tiết /wp-content": 4 dòng Plugins / Themes / Media (uploads) / Khác (phần còn lại) — mỗi dòng: tên + đường dẫn tương đối monospace · thanh `laca-progress` theo % trong wp-content · số + "Chiếm X%".
- "Top 10 theo dung lượng" cho 3 nhóm (Plugins, Themes, Uploads theo năm/tháng): danh sách thư mục con lớn nhất với thanh tỉ lệ trong nhóm.
- Chưa có số liệu → `laca-empty` + nút "Tính ngay".

**File cỡ lớn (> 50 MB)**
- Nút "Quét" → AJAX quét theo lô (tránh timeout): duyệt `WP_CONTENT_DIR` + `ABSPATH` cấp 1 (KHÔNG đi sâu vào `wp-admin`, `wp-includes`), bỏ qua symlink, mỗi lượt tối đa ~10 giây rồi trả con trỏ để JS gọi tiếp; hiển thị tiến độ ("Đã quét N thư mục…").
- Kết quả: 2 `laca-stat` (Tổng dung lượng file lớn, Số file) + `laca-alert` warning "Xem kỹ tên file trước khi xoá." + bảng: checkbox · Tên file + badge loại (Backup / Log / Video / Lưu trữ / Thư viện build (node_modules) / Khác — đoán theo đuôi/đường dẫn) · đường dẫn tương đối monospace · dung lượng · ngày sửa.
- **Xoá (bắt buộc an toàn)**:
  - Chỉ cho chọn checkbox với file nằm trong VÙNG ĐƯỢC XOÁ: `wp-content/uploads/`, `wp-content/cache/`, `wp-content/upgrade/`, `wp-content/backups*/`, `wp-content/ai1wm-backups/`, file `*.log` / `*.sql` / `*.sql.gz` / `*.zip` / `*.tar` / `*.tar.gz` / `*.wpress` ở cấp 1 của `wp-content/` hoặc `ABSPATH`. File khác (trong `plugins/`, `themes/`, `mu-plugins/`, `languages/`, core, mọi file `.php/.phtml/.htaccess/.user.ini`) hiển thị checkbox bị khoá + tooltip "Không xoá được ở đây — xử lý qua FTP/hosting" (vd `node_modules` trong theme).
  - Server kiểm lại TỪNG đường dẫn: `realpath()` phải nằm trong vùng được xoá ở trên, không phải symlink, không phải thư mục, đuôi không bị cấm; sai bất kỳ điều kiện nào → từ chối file đó.
  - Nút "Xoá file đã chọn" (danger) → modal/confirm liệt kê tên + tổng dung lượng → AJAX `laca_storage_delete_files` (nonce, `manage_options`), xoá bằng `wp_delete_file()`; trả kết quả từng file (đã xoá / bị từ chối + lý do). Ghi 1 dòng vào `ConfigChangeLog::log('storage_delete', '', 'tên file — dung lượng')` cho mỗi file đã xoá.
  - Không có thao tác xoá hàng loạt "tất cả".

### 3.2 Backend
- Mở rộng `StorageHealth` (không phá API cũ đang dùng ở widget): thêm tách nhỏ plugins/themes/uploads/khác + top 10 mỗi nhóm vào cùng cache 12h. Giới hạn thời gian 15s như hiện có; vượt → đánh dấu "chưa quét hết".
- Class mới `LargeFileScanner` (cùng thư mục) cho quét theo lô + xoá an toàn; danh sách vùng được xoá + đuôi cấm đặt thành hằng/hàm duy nhất, có filter `laca_storage_deletable_roots` để mở rộng.
- Widget Dashboard "Lưu trữ & quyền file" giữ nguyên, thêm link "Chi tiết →" tới tab này.

---

## 4. Trang "Tối ưu" — làm rõ Tải ảnh chậm & các công tắc

Không dựng lại trang (vẫn Carbon Fields ở `AdminSettings.php`, container `laca-tools`). Chỉ sửa NHÃN + MÔ TẢ cho dễ hiểu theo lối Sitevorx (người không chuyên đọc được), không đổi tên field/option:
- `enable_optimize_images` → "Tải ảnh chậm (Lazy Load) cho ảnh đại diện" — "Chỉ tải ảnh khi người xem cuộn tới. Trang hiện nhanh hơn, tiết kiệm băng thông."
- `enable_optimize_content_images` → "Tải ảnh chậm cho ảnh trong bài viết" — mô tả tương tự.
- `disable_emoji` → "Tắt Emoji của WordPress" — "Bỏ ~15KB script/CSS emoji; emoji vẫn hiển thị bằng font của thiết bị."
- `disable_use_jquery_migrate` → "Bỏ thư viện JavaScript cũ (jQuery Migrate)" — "Chỉ cần cho theme/plugin rất cũ (~10KB). Bật xong kiểm tra site; lỗi thì tắt lại."
- Kiểm tra các field mô tả còn tiếng Anh/khó hiểu khác trong cùng container (vd `remove_protocols`, `remove_relative_domain`, `enable_advanced_resource_hints`, `enable_register_service_worker`, `enable_optimize_memory_usage`) và viết lại mô tả 1–2 câu tiếng Việt dễ hiểu, nói rõ rủi ro nếu có.
- Thêm 1 dòng `laca-alert` info đầu container: "Gỡ oEmbed, ẩn phiên bản WordPress, tắt pingback nằm ở Bảo mật → Gia cố." (link) — để người dùng không tìm 2 nơi.
- Mọi nhãn/mô tả qua `__()`.

---

## 5. Đồng bộ, build, kiểm tra

1. B trước → copy A; thêm mọi file mới/sửa vào `/tmp/laca-parity/parity-check.sh`; chạy từ `/Users/lacadev/Documents/wordpress` → `Differing/Missing: 0`. **Chạy `cmp` lần cuối SAU bước cuối cùng** (lần trước lệch dòng trống cuối file).
2. `php -l` mọi file PHP sửa/tạo. Build A `yarn build`; B `yarn build` trong `lacadev-client-child`. `wp i18n make-pot` cả 2.
3. Test (cả 2 site, Playwright, kiểm `isVisible`), chụp 1440px + 375px vào `/tmp/laca-parity/dashboard/screenshots/`:
   - Bấm "Laca Admin" ở sidebar → mở "Tổng quan"; `admin.php?page=laca-admin` vẫn mở "Cấu hình chung".
   - Dashboard khi CHƯA có cache audit/dung lượng (xoá transient test) → hiện trạng thái chưa có số liệu, không timeout; bấm "Kiểm tra lại"/"Tính ngay" → có số. Đo thời gian tải trang dashboard khi có cache (< 1s trên local, ghi số đo).
   - 4 thẻ KPI + "Việc nên làm" khớp dữ liệu thật (đối chiếu option từng lớp bảo mật).
   - Tab Thông số máy chủ hiển thị đủ; nút Sao chép.
   - Tab Dung lượng: tính, top 10, quét file lớn trên hub (sẽ thấy file trong `node_modules` của theme → checkbox phải BỊ KHOÁ). Test xoá: tạo file tạm 51MB `wp-content/uploads/laca-test-large.bin` (bằng `dd`/`truncate` — đây là file do test tạo) → quét thấy → xoá qua UI → biến mất + có dòng nhật ký. Thử gọi AJAX xoá với đường dẫn `../wp-config.php`, file trong `plugins/`, symlink → đều bị từ chối (ghi output).
   - Trang Tối ưu: nhãn mới hiển thị, lưu vẫn đúng option cũ.
4. Hồi quy: mở toàn bộ trang admin trong menu cả 2 site + frontend + `wp-login.php` → không Fatal; `logs/php/error.log` không Fatal/Warning mới.
5. Trả mọi transient/option thay đổi khi test về như cũ.

## 6. Báo cáo

`/tmp/laca-parity/dashboard/REPORT.md`: file tạo/sửa (cả 2 theme), nguồn dữ liệu của từng KPI/lớp bảo mật, kết quả từng mục test (pass/fail + output thật, số đo thời gian), đường dẫn ảnh, chỗ lệch plan + lý do. Gửi progress qua agent-bridge sau mỗi mục 1→4 và khi xong.
