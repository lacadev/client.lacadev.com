# PLAN — Hiệu năng frontend & sẵn sàng cho cache toàn trang (11/2026)

> Viết plan: Claude Code · Làm: Antigravity · Review: Claude Code
> Mục tiêu đo được (khách chưa đăng nhập, trang chủ + 1 bài viết, site local, CHƯA có page cache/object cache):
> - **≤ 35 query** mỗi trang (hiện 113–128), **0 lệnh ghi DB** (hiện 1 ở trang bài viết), **< 100 ms PHP** (hiện ~200 ms).
> - Bật cache toàn trang (HTML cũ 48 giờ) thì **tìm kiếm, form liên hệ, các AJAX công khai vẫn chạy đúng**.
> Áp dụng CẢ 2 theme, file dùng chung GIỐNG HỆT nhau, làm ở B rồi copy sang A:
> - A (hub): `/Users/lacadev/Documents/wordpress/lacadev/app/public/wp-content/themes/lacadev`
> - B (client): `/Users/lacadev/Documents/wordpress/lacadev-client/app/public/wp-content/themes/lacadev-client`

---

## 0. Luật bắt buộc

1. Giữ toàn bộ luật mục 0 của các plan trước (`PLAN-SITE-CONFIG-PACKAGE-2026-11.md` §0): phạm vi sửa, không xoá file, không commit, không cài package, giữ bản vá cũ, i18n, bảo mật AJAX, `cmp` sau bước cuối, dọn dữ liệu test.
2. **Không đổi hành vi hiển thị**: HTML frontend sau khi sửa phải giống trước (trừ nonce/thuộc tính liên quan cache). So bằng diff HTML trang chủ + 1 bài + trang tìm kiếm trước/sau (bỏ qua dòng chứa nonce/ver).
3. Không thêm query/công việc nào vào request frontend; mọi việc nặng chuyển sang cron hoặc hành động admin.
4. Đo bằng script `/tmp/laca-parity/perf.php` và `/tmp/laca-parity/perf2.php` (đã có — đọc trước để dùng; chạy bằng PHP 8.3 của Local với `-d mysqli.default_socket=…/run/<site-id>/mysql/mysqld.sock`; site-id: lacadev `r_Z9Sldo9`, lacadev-client `RqSXliHDN`). Ghi số đo TRƯỚC khi sửa và SAU từng mục.

---

## 1. Bộ nhớ đệm theme options (Carbon Fields) — mục tiêu bỏ ~43 query

**Hiện trạng:** `Carbon_Fields\Datastore\Theme_Options_Datastore::load()` chạy 1 câu SQL trực tiếp cho MỖI lần `carbon_get_theme_option()` (không qua object cache). Theme gọi 55 chỗ trực tiếp + 34 chỗ qua `getOption()` (`app/helpers/template_tags.php:164`, nối `currentLanguage()`).

**Làm:** class mới `app/src/Helpers/ThemeOptionsCache.php`:
1. **Bản chụp**: 1 option `laca_theme_options_snapshot` (autoload **yes**) chứa mảng `[field_name => value đã định dạng như carbon_get_theme_option trả về]` cho TẤT CẢ field của mọi container `theme_options` (đọc danh sách field như `ConfigRegistry::getCarbonFieldsData()` — dùng lại, không viết lại). Field `complex`/`association`/`media_gallery` lưu đúng cấu trúc mà `carbon_get_theme_option` trả.
2. **Tạo lại bản chụp** khi: lưu bất kỳ container theme options (`carbon_fields_theme_options_container_saved`), sau khi nhập "Bộ cấu hình site", sau `update_option`/`delete_option` của option bắt đầu `_` thuộc field CF (dùng `ConfigRegistry` để biết), khi đổi ngôn ngữ Polylang, khi đổi theme. Tạo lại chỉ chạy ở admin/cron/AJAX admin, KHÔNG ở request frontend. Nếu bản chụp chưa có/hỏng ở frontend → đọc kiểu cũ (fallback) và đặt cờ để cron/admin kế tiếp tạo lại (không tạo ở frontend).
3. **Chặn đọc trùng ở request**: dùng filter của Carbon Fields nếu có điểm can thiệp trước `load()` (đọc `vendor/htmlburger/carbon-fields` — vd filter giá trị field/`carbon_fields_should_load_field_value`/Repository); nếu KHÔNG có điểm can thiệp an toàn thì:
   - Sửa `getOption()` để đọc từ `ThemeOptionsCache::get($name)`.
   - Thêm helper `laca_theme_option($name, $default = null)` và **thay các lời gọi `carbon_get_theme_option()` CHẠY Ở FRONTEND** (template, helper, setup, class chạy ở frontend) bằng helper này. Không cần thay ở code chỉ chạy trong admin. Lập bảng: file:dòng, frontend/admin, đã thay hay chưa.
4. Giữ đúng ngữ nghĩa đa ngôn ngữ: `getOption($name)` vẫn đọc `$name . currentLanguage()`; field không có hậu tố → giữ cách fallback hiện tại (`getDefaultImageId()` …).
5. Đảm bảo KHÔNG gọi `carbon_get_*` trước `carbon_fields_fields_registered` (bản chụp đọc thẳng option nên an toàn ở mọi thời điểm).

**Kiểm:** đếm query của `Carbon_Fields\…Datastore` trên frontend = 0 (khi đã có bản chụp). Đổi 1 field trong Laca Theme → lưu → frontend hiện giá trị mới ngay (bản chụp được làm mới). Diff HTML trước/sau = chỉ khác nonce/ver.

---

## 2. Option chưa tồn tại bị đọc lại mỗi request — mục tiêu bỏ ~30 query

**Hiện trạng:** `Optimize`, `Security`, `AdminSettings`, `ThemeSettings`, `GoogleServices\Tracking`, `LoginHoneypot`, `LacaSeo\Core\Settings`, `OpenGraph\Slack` … gọi `get_option()` cho option chưa từng lưu ⇒ mỗi option thiếu = 1 query/request (không có object cache).

**Làm:**
1. Chạy `perf2.php` (sửa để in thêm tên option của các query `SELECT option_value FROM wp_options WHERE option_name = …`) → lập danh sách option thiếu bị đọc ở frontend.
2. Option thuộc field Carbon Fields → đọc qua bản chụp mục 1 (không gọi `get_option('_x')` lẻ).
3. Option ngoài CF (vd `laca_login_honeypot`, `laca_security_headers`, `laca_login_policy`, option SEO…) → class mới `app/src/Helpers/OptionDefaults.php`: danh sách option + giá trị mặc định ĐÚNG như code đang coi là mặc định; khi theme được kích hoạt/nâng version (`after_switch_theme` + kiểm 1 option version `laca_option_defaults_version`) thì `add_option($name, $default, '', true)` cho option CHƯA tồn tại. **Không ghi đè** option đã có. Giá trị mặc định phải giữ đúng hành vi hiện tại (vd honeypot `'yes'`, headers = defaults() của SecurityHeaders).
4. Option tạm thời kiểu flag (vd `laca_*_flushed`) chỉ đọc ở admin → chuyển việc đọc khỏi frontend nếu được.

**Kiểm:** frontend không còn query `SELECT … option_name = 'x'` cho option không tồn tại (in danh sách còn lại, giải thích từng cái).

---

## 3. Bảng log kiểm tra mỗi request — bỏ 2–3 query `SHOW TABLES`

`EmailLogTable::install()`, `LoginLogTable::install()` (gọi không điều kiện ở `theme/functions.php` ~dòng 553 của B) đang `SHOW TABLES LIKE` ở mỗi request. Làm giống `ContactFormTable::install()` đã sửa: option version (`laca_email_log_table_version`, `laca_login_log_table_version`), khớp version ⇒ `return`; tự chữa chỉ ở `is_admin()` với transient 12 giờ; tham số `$force` cho `after_switch_theme`. Kiểm `theme/functions.php` của A có lời gọi tương tự không, xử lý cùng kiểu.

---

## 4. Bộ đếm lượt xem — 0 lệnh ghi DB ở request trang, đúng khi có page cache

**Hiện trạng:** `ThemeSettings.php:24` gọi `updateViewCount()` (`app/helpers/template_tags.php:122`) ⇒ `update_post_meta('_gm_view_count')` mỗi lượt xem (ghi DB, khoá dòng khi đông; và KHÔNG chạy khi có page cache ⇒ số sai).

**Làm:**
1. Bỏ ghi ở request trang. Thêm JS nhỏ (vanilla, trong bundle theme hiện có, không thư viện) chạy trên trang bài viết/CPT có đếm: sau khi trang tải xong + trang hiển thị ≥ 1 giây, gửi `navigator.sendBeacon` (fallback `fetch keepalive`) tới REST endpoint `POST /wp-json/laca/v1/view` với `{id}`. Không cần nonce (thao tác công khai, không tiết lộ dữ liệu); chống spam: rate limit theo IP+post bằng transient/object cache 1 lượt / 30 phút / IP / bài, bỏ qua bot (UA chứa bot/crawl/spider/preview), bỏ qua user đã đăng nhập có `edit_posts`.
2. Endpoint KHÔNG ghi post meta ngay: cộng dồn vào bộ đệm — nếu có object cache bền (`wp_using_ext_object_cache()`) dùng `wp_cache_incr` theo nhóm; nếu không, cộng vào 1 option buffer `laca_view_buffer` (autoload no) bằng 1 câu `INSERT … ON DUPLICATE KEY UPDATE` hoặc bảng nhỏ chuyên dụng — chọn cách ít khoá nhất, ghi rõ lý do.
3. Cron mới `laca_flush_view_counts` (mỗi 5 phút) cộng bộ đệm vào `_gm_view_count` theo lô rồi xoá bộ đệm. Có khoá chống chạy chồng.
4. Giữ nguyên cách đọc/hiển thị lượt xem hiện có (`template_tags.php:111`, `functions.php:419/572`, `ListTableEnhancements`).
5. Có công tắc "Đếm lượt xem bài viết" (mặc định bật như hiện tại) ở trang Tối ưu; tắt ⇒ không in JS, không đăng ký endpoint.

**Kiểm:** request trang bài viết = 0 WRITES. Gọi endpoint 3 lần cùng IP ⇒ chỉ tính 1. Chạy cron ⇒ `_gm_view_count` tăng đúng. Bot UA ⇒ không tính.

---

## 5. Nonce trong HTML — chạy đúng khi trang được cache lâu

**Hiện trạng:** HTML công khai chứa nonce: `themeData.nonce`, `themeSearch.nonce` (`theme_search_nonce`, `theme_nonce`), form liên hệ `_nonce` (`laca_contact_submit_nonce`), có thể còn chỗ khác. Page cache giữ HTML > 12–24h ⇒ nonce hết hạn ⇒ AJAX trả 403/-1.

**Làm:**
1. Liệt kê TẤT CẢ nonce xuất hiện trong HTML frontend cho khách (grep output trang chủ, bài viết, trang có form liên hệ, trang tìm kiếm, trang lưu trữ) + mọi `wp_ajax_nopriv_*` (hiện có: `ajax_search, get_page, gm_add_to_cart, laca_cf_submit, laca_check_submission_status, laca_contact_submit, laca_filter_projects, load_more_search, laca_seo_overlay_thumb, rank_math_overlay_thumb, user_login, user_register, google_login, social_login_callback, google_admin_callback`). Lập bảng: action · đọc hay ghi · có cần nonce không · cách xử lý.
2. **Thao tác chỉ ĐỌC dữ liệu công khai** (tìm kiếm, load more, lọc, get_page, overlay thumb…): bỏ kiểm nonce cho khách (nonce không bảo vệ gì với dữ liệu công khai); giữ sanitize + giới hạn (số kết quả tối đa, độ dài từ khoá, rate limit nhẹ theo IP nếu tốn tài nguyên). Giữ nonce nếu user đã đăng nhập gửi (không bắt buộc).
3. **Thao tác GHI** (gửi form liên hệ, đăng ký, đăng nhập, giỏ hàng): lấy nonce MỚI lúc cần — endpoint REST `GET /wp-json/laca/v1/nonce?action=…` (chỉ cấp cho danh sách action cho phép, header `Cache-Control: no-store, private`), JS gọi khi người dùng focus vào form lần đầu / trước khi submit; nếu server trả lỗi nonce hết hạn ⇒ JS tự lấy nonce mới và gửi lại 1 lần. Form liên hệ vẫn giữ honeypot + rate limit hiện có.
4. Trang có nội dung riêng theo người (giỏ hàng, tài khoản) — đảm bảo gửi `nocache_headers()`/`DONOTCACHEPAGE`.
5. Không in nonce vào HTML công khai nữa (trừ khi user đã đăng nhập).

**Kiểm (bắt buộc, giả lập cache cũ):** lưu HTML trang có form/tìm kiếm, sửa giờ hệ thống KHÔNG được — thay vào đó dùng filter `nonce_life` = 1 giây trong mu-plugin TẠM ở `/tmp` nạp qua `-d auto_prepend_file` cho CLI, hoặc kiểm bằng cách gửi request với nonce rác: tìm kiếm vẫn trả kết quả; form liên hệ tự lấy nonce mới và gửi thành công; đăng nhập/đăng ký vẫn chặn khi không có nonce hợp lệ.

---

## 6. Tương thích cache toàn trang + xoá cache khi đổi nội dung

Class mới `app/src/Settings/LacaTools/Performance/CacheCompat.php`:
1. **Xoá cache khi đổi nội dung** (`save_post` publish/update/trash, `edited_term`, `wp_update_nav_menu`, lưu Laca Theme options, nhập Bộ cấu hình site, đổi `laca_maintenance_mode`): gọi hàm purge của cache có mặt — chỉ gọi khi hàm/hành động tồn tại, không cài gì:
   - LiteSpeed: `do_action('litespeed_purge_post', $id)` / `do_action('litespeed_purge_all')`.
   - Nginx Helper: `do_action('rt_nginx_helper_purge_all')` (hoặc purge URL nếu có).
   - WP Rocket: `rocket_clean_post($id)` / `rocket_clean_domain()`.
   - W3TC: `w3tc_flush_post($id)` / `w3tc_flush_all()`.
   - WP Super Cache: `wp_cache_post_change($id)`.
   - Cloudflare (plugin chính thức): `do_action('cloudflare_purge_by_url', $urls)` nếu có.
   Theo dõi số lần purge trong request, gom về 1 lần cho mỗi request.
2. **Header cache cho khách**: trang công khai không gửi cookie/`Set-Cookie`, không `session_start` (rà toàn theme), Vary đúng. Trang tài khoản/giỏ/thanh toán/kết quả form ⇒ `nocache_headers()`.
3. Trang **Laca Admin → Hệ thống → tab Thông số máy chủ**: thêm card "Hiệu năng & cache" (chỉ đọc): object cache bền có/không (`wp_using_ext_object_cache()`), loại page cache phát hiện được (plugin/hằng/headers `x-litespeed-cache`, `x-nginx-cache`, `cf-cache-status` — lấy bằng 1 `wp_remote_head(home_url())` khi bấm nút, không tự chạy), OPcache, `DISABLE_WP_CRON`, gzip/brotli (header `content-encoding` khi bấm nút), số query + thời gian của 1 request trang chủ đo khi bấm nút "Đo hiệu năng trang chủ" (gọi loopback có tham số bí mật tạm thời — hoặc chỉ hiển thị `timer_stop()`/`get_num_queries()` của chính request admin nếu loopback phức tạp; chọn cách an toàn, ghi lý do). Mỗi dòng có khuyến nghị ngắn tiếng Việt.
4. Thêm tài liệu `doc/HOSTING-PERFORMANCE-GUIDE.md` (cả 2 theme), ngắn gọn tiếng Việt: cấu hình khuyên dùng (page cache LiteSpeed/Nginx FastCGI/Cloudflare APO, Redis object cache, OPcache, PHP 8.3, cron thật + `DISABLE_WP_CRON` + dòng crontab mẫu dùng `wp-cron.php`, gzip/brotli, CDN cho ảnh/CSS/JS), những URL KHÔNG được cache (`/wp-admin`, `wp-login.php`/slug đăng nhập tuỳ chỉnh, REST `laca/v1/nonce`, `laca/v1/view`, trang giỏ/tài khoản), danh sách cookie bỏ qua cache.

---

## 7. WP-Cron trên site đông khách

Rà 5 tác vụ cron của theme (`laca_tracker_hourly_scan`, `laca_tracker_heartbeat`, `laca_tracker_daily_digest`, `laca_discord_hub_update_check`, `lacadev_weekly_deep_audit` + cron mới mục 4): mỗi tác vụ phải có khoá chống chạy chồng và giới hạn thời gian/lô; quét file chạy theo lô. Không đổi lịch. Ghi bảng (tác vụ, tần suất, thời gian chạy đo được trên local, có khoá chưa) vào báo cáo. Card ở mục 6.3 hiện khuyến nghị khi `DISABLE_WP_CRON` chưa bật.

---

## 8. Đồng bộ, build, kiểm tra

1. `php -l`, build A + B child, `wp i18n make-pot`, parity `cmp` = 0 **sau bước cuối** (chú ý: file phía B hay bị thêm dòng trống cuối sau khi đồng bộ — chạy `cmp` lần cuối sau khi mọi thứ xong).
2. Bảng số đo TRƯỚC/SAU cho cả 2 site (trang chủ, 1 bài viết, trang tìm kiếm `?s=hello`, 1 trang lưu trữ): queries, writes, ms (chạy 3 lần lấy trung vị), peak MB. Đạt mục tiêu đầu plan; mục nào không đạt ⇒ giải thích + liệt kê query còn lại theo nguồn.
3. Diff HTML trước/sau (mục 0.2).
4. Test chức năng trên trình duyệt (Playwright, `isVisible`): tìm kiếm AJAX, load more, form liên hệ gửi thành công (kiểm submission vào DB rồi xoá submission test), đăng nhập (kể cả honeypot), lượt xem tăng sau cron, Laca Theme đổi field → frontend đổi ngay, card "Hiệu năng & cache".
5. Test giả lập cache (mục 5 "Kiểm").
6. Hồi quy: mở toàn bộ trang admin 2 site + frontend + `wp-login.php` ⇒ không Fatal/Warning mới.
7. Trả 2 site về như trước (option test, submission test, bộ đệm view, transient).

## 9. Báo cáo

`/tmp/laca-parity/perf/REPORT.md`: file tạo/sửa; bảng số đo trước/sau; bảng `carbon_get_theme_option` đã thay; bảng option mặc định đã thêm; bảng nonce/AJAX công khai và cách xử lý; bảng cron; output test thật; ảnh card "Hiệu năng & cache" (1440 + 375) ở `/tmp/laca-parity/perf/screenshots/`; chỗ lệch plan + lý do. Progress qua agent-bridge sau mỗi mục 1→7 và khi xong.
