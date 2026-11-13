# PLAN — "Bộ cấu hình site": xuất/nhập chung Laca Admin + Laca SEO (11/2026)

> Viết plan: Claude Code · Làm: Antigravity · Review: Claude Code
> Mục tiêu chủ dự án: tạo site mới → nhập 1 file JSON → site có đúng cấu hình (Laca Theme/Laca Admin, bảo mật, tối ưu, form liên hệ mẫu, Laca SEO) mà không phải cấu hình tay từng trang.
> Áp dụng CẢ 2 theme, file dùng chung GIỐNG HỆT nhau. Làm ở B rồi copy sang A:
> - A (hub): `/Users/lacadev/Documents/wordpress/lacadev/app/public/wp-content/themes/lacadev`
> - B (client): `/Users/lacadev/Documents/wordpress/lacadev-client/app/public/wp-content/themes/lacadev-client`

---

## 0. Luật bắt buộc

1. Giữ toàn bộ luật mục 0 của `PLAN-HARDENING-WORDFENCE-STYLE-2026-11.md` và `PLAN-LACA-DASHBOARD-SYSTEM-STORAGE-2026-11.md` (phạm vi, không xoá file, không commit, không cài package, giữ bản vá cũ, không `carbon_get_*` lúc nạp theme, i18n `__()` domain `laca` msgid tiếng Việt, nonce + `manage_options`, UI flat dùng `laca-*`, test `isVisible`, `cmp` sau bước cuối, dọn dữ liệu test).
2. **Danh sách TRẮNG, không danh sách đen.** Chỉ xuất/nhập option có trong "Sổ đăng ký cấu hình" (mục 2). Option không có trong sổ → không xuất, và khi nhập thì BỎ QUA (báo "không thuộc bộ cấu hình"). Tuyệt đối không ghi option core WP (`siteurl`, `home`, `admin_email`, `default_role`, `users_can_register`, `active_plugins`, `template`, …) dù file có chứa.
3. **Không bao giờ xuất/nhập danh tính & trạng thái của site** (mục 2.3) — đặc biệt các khoá tracker/hub. Nhập nhầm sẽ làm site mới báo cáo về hub dưới danh nghĩa project của site cũ.
4. **Luôn sao lưu trước khi áp dụng** và có nút khôi phục.
5. **Sửa lỗ hổng hiện tại** của `ConfigExportImport`: đang xuất MỌI option `laca_%` và `_%` (gồm `_laca_project_id`, `_laca_tracker_endpoint`, `_laca_key_*`, `_laca_tracker_file_baseline`, index IP bị khoá, log, phiên bản DB…) và cho nhập mọi tên bắt đầu `laca_`/`_`. Sau plan này cả xuất lẫn nhập đều đi qua Sổ đăng ký.

---

## 1. Định dạng file

```json
{
  "format": "laca-site-config",
  "format_version": 1,
  "exported_at": "2026-11-12T10:00:00+07:00",
  "source": { "home_url": "https://site-cu.vn", "theme": "lacadev-client", "theme_version": "3.8.1", "wp_version": "7.1.4", "locale": "vi" },
  "includes_secrets": false,
  "sections": {
    "theme_options": { "<option_name>": <value>, ... },
    "security":      { ... },
    "tools":         { ... },
    "notifications": { ... },
    "contact_forms": [ { "name": "...", "fields": [...], "notify_email": "...", ... } ],
    "custom_post_types": { ... },
    "seo":           <payload Laca SEO Exporter, giữ nguyên định dạng của nó>
  },
  "media": { "<attachment_id>": { "url": "...", "filename": "...", "alt": "...", "title": "...", "mime": "image/png" } },
  "manual_fields": [ { "option": "...", "label": "...", "reason": "page_id" } ]
}
```
- Tên file: `laca-site-config-{host}-{YYYYmmdd-His}.json`; có secret thì thêm hậu tố `-CO-MAT-KHAU`.
- Giới hạn file nhập 5 MB, phải là JSON hợp lệ, `format` đúng.
- **Tương thích ngược khi nhập**: (a) file cũ của `ConfigExportImport` (có khoá `config`) → coi như các section Laca Admin, vẫn lọc qua Sổ đăng ký; (b) file JSON của Laca SEO (định dạng của `Features/LacaSeo/ImportExport/Exporter`) → coi như section `seo`.

---

## 2. Sổ đăng ký cấu hình — class mới `app/src/Settings/LacaTools/Management/SiteConfig/ConfigRegistry.php`

### 2.1 Theme options (Carbon Fields) — tự động, theo field THẬT đã đăng ký
- Chỉ chạy sau `carbon_fields_fields_registered`. Lấy toàn bộ container `theme_options` của theme qua repository của Carbon Fields (đọc `vendor/htmlburger/carbon-fields` để dùng đúng API: container repository → `get_fields()` → `get_base_name()`/`get_type()`; đệ quy field con của `complex`).
- Một option thuộc theme options nếu tên = `_{base_name}` hoặc bắt đầu bằng `_{base_name}|` (cách Carbon Fields lưu field complex/đa ngôn ngữ). Lấy giá trị bằng `$wpdb` theo LIKE đã `esc_like` (không gọi `carbon_get_*` từng field).
- Gán **section** theo container: container bảo mật/2FA/đăng nhập → `security`; tối ưu/`laca-tools` → `tools`; thông báo/Discord/Zalo/Telegram/Slack → `notifications`; còn lại → `theme_options`. Bảng ánh xạ container → section đặt thành 1 mảng hằng trong class, ghi rõ trong báo cáo.
- Ghi nhận **kiểu field** để xử lý ở mục 4: `image`/`file`/`media_gallery` (ID đính kèm), `association`, `select`/`radio` có nguồn là trang/bài (ID nội dung) → `manual_fields`.

### 2.2 Option cấu hình ngoài Carbon Fields — danh sách khai báo tay
Antigravity **grep toàn bộ `get_option(`/`update_option(`/`add_option(` trong cả 2 theme** và phân loại TỪNG tên option vào 1 trong 3 nhóm (đưa bảng đầy đủ vào báo cáo): **Cấu hình** (đưa vào sổ), **Danh tính site**, **Trạng thái/chạy nền**. Tối thiểu các option sau là Cấu hình:
- `security`: `laca_login_policy`, `laca_login_guard_settings`, `laca_login_honeypot`, `laca_security_headers`, `laca_2fa_master_enabled`, các option hardening (REST/XML-RPC/oEmbed/pingback/bloat — tìm đúng tên trong `SecurityManager::ajaxSaveHardeningSettings`), `laca_enable_custom_login`, `laca_login_slug` (gắn cờ "URL đăng nhập sẽ đổi" ở màn xem trước).
- `tools`: `laca_db_cleaner_schedule` (khi nhập phải gọi lại logic lên/huỷ lịch cron của `DatabaseCleaner`, không chỉ ghi option).
- `notifications`: phần KHÔNG bí mật của `laca_discord_hub` (`Settings::OPTION`). **Không** đụng `laca_discord_hub_webhooks` (mã hoá bằng AUTH_KEY của site cũ — copy sang sẽ hỏng).
- `custom_post_types`: option lưu định nghĩa CPT động của `DynamicCptManager` (tìm tên thật). Sau khi nhập → `flush_rewrite_rules()` một lần.
- Maintenance mode: option cấu hình của `MaintenanceModeManager` nhưng **luôn nhập ở trạng thái TẮT** (không để site mới bị khoá ngay).

### 2.3 Không bao giờ xuất/nhập (cứng trong code, ưu tiên cao hơn mọi thứ)
- Danh tính / kết nối hub: mọi option chứa `tracker`, `project_id`, `_laca_key_`, `laca_key_`, `laca_sync_key`, `laca_catalog_key`, `laca_client_config_paste`, khoá API Block Sync/Catalog, `laca_known_login_ips`, cấu hình ThemeUpdater URL.
- Trạng thái: log, queue, `*_version`, `*_last_run*`, `*baseline*`, `laca_login_guard_locked_ips`, `laca_config_change_log`, transient, `laca_cf_default_form_id`, `laca_client_role_version`, `laca_pwa_rewrite_flushed`, sao lưu của SEO/plan này…
- Secret mặc định: theo `SECRET_PATTERN`/`SECRET_BLOCKLIST` hiện có (trừ khi bật mục 3.3).
- Viết unit test CLI chứng minh: với DB thật của 2 site, KHÔNG option nào ở 2.3 lọt vào file xuất.

---

## 3. Xuất — class `SiteConfig/SiteConfigExporter.php`

1. Chọn section (checkbox, mặc định chọn hết trừ secret): Giao diện & Laca Theme · Bảo mật · Công cụ & tối ưu · Thông báo · Form liên hệ (mẫu form, KHÔNG gồm tin khách gửi) · Custom Post Type động · Laca SEO (toàn bộ các phần mà trang Import/Export của Laca SEO đang có) · Kèm ảnh (logo, ảnh mặc định… của các field ảnh) · Kèm mật khẩu/khoá API.
2. **Form liên hệ**: lấy từ `ContactFormTable::getAllForms()` — chỉ các cột định nghĩa form (name, fields, notify_email, email_*_subject/body, style_settings, is_active). Không lấy id, không lấy submission.
3. **Kèm mật khẩu/khoá API** (mặc định TẮT): xuất giá trị đã GIẢI MÃ (`Crypto::decryptTagged` cho giá trị `laca:v1:`; giải mã lỗi → bỏ field đó, ghi vào `manual_fields`), đặt `includes_secrets: true`, tên file có hậu tố. UI hiện `laca-alert` danger: "File sẽ chứa mật khẩu SMTP và khoá API ở dạng đọc được. Chỉ gửi qua kênh an toàn và xoá sau khi dùng." Không bao giờ xuất: secret 2FA của user, webhook Discord, khoá tracker/hub.
4. **Ảnh**: với mỗi ID đính kèm trong field kiểu ảnh/file → thêm vào `media` (url gốc, filename, alt, title, mime). Không nhúng nội dung ảnh vào JSON.
5. **SEO**: gọi `\App\Features\LacaSeo\ImportExport\Exporter::generateExport($sections)` (đọc hàm để truyền đúng danh sách section), đặt payload vào `sections.seo`. Không viết lại logic xuất SEO.
6. Tải về bằng `admin-post.php` (stream JSON, `Content-Disposition`), không qua AJAX base64.

---

## 4. Nhập — class `SiteConfig/SiteConfigImporter.php`

### 4.1 Bước 1 — Tải lên & xem trước (không ghi gì)
- Upload file (admin-post hoặc AJAX multipart, nonce, cap, kiểm MIME/kích thước/`format`).
- Lưu payload đã kiểm tạm trong transient theo user (30 phút) + token; không lưu file vào uploads.
- Màn xem trước theo từng section: số mục Mới / Khác / Giống / Bị bỏ qua; bảng chi tiết (ẩn dòng "Giống" mặc định); danh sách **Bị bỏ qua** kèm lý do (không thuộc bộ cấu hình / danh tính site / field không tồn tại trên site này / secret không được phép).
- Cảnh báo nổi bật nếu: đổi `laca_login_slug` (hiện URL đăng nhập MỚI), bật chính sách khoá IP mạnh, bật bắt buộc 2FA, file chứa secret, file đến từ theme khác (`source.theme` ≠ theme hiện tại — vẫn cho nhập, chỉ các field trùng tên).
- Tuỳ chọn khi áp dụng (checkbox):
  - "Thay domain cũ bằng domain site này" (mặc định BẬT) — thay `source.home_url` (cả http/https, có/không `www`, dạng đã escape `\/`) bằng `home_url()` trong mọi giá trị chuỗi, đệ quy qua mảng; dùng lại cách làm `deepReplaceString` của Laca SEO Importer (gọi chung 1 helper, không chép 2 bản).
  - "Tải ảnh (logo, ảnh mặc định…) về thư viện Media" (mặc định BẬT nếu file có `media`).
  - "Giữ thông tin xác thực Google của site này" cho phần SEO (mặc định BẬT — truyền đúng tham số `preserveSiteCredentials` của Laca SEO Importer).
  - Chọn section nào sẽ áp dụng.

### 4.2 Bước 2 — Áp dụng
1. **Sao lưu tự động**: chụp giá trị HIỆN TẠI của mọi option sắp bị ghi (kể cả "chưa tồn tại" → ghi nhận để khôi phục bằng `delete_option`) + danh sách form hiện có; lưu option `laca_site_config_backups` (autoload `no`, tối đa 5 bản, mỗi bản có thời gian, người làm, tên file nguồn). SEO dùng cơ chế backup sẵn có của Laca SEO Importer.
2. Ghi theme/security/tools/notifications/cpt bằng `update_option` (giữ autoload theo option gốc nếu đã tồn tại; option CF mới → để mặc định như Carbon Fields).
3. **Ảnh**: với mỗi ID trong `media` được tham chiếu: tìm trước attachment cùng `filename` đã có trên site (tránh tải trùng khi nhập lại) → không có thì `download_url()` + `media_handle_sideload()` (timeout 20s/ảnh, tối đa 30 ảnh, chỉ mime ảnh/pdf, URL phải http/https). Tạo bảng map ID cũ → ID mới rồi thay trong giá trị field ảnh (kể cả trong complex). Ảnh lỗi → field để trống + đưa vào kết quả "cần chọn lại".
4. **Form liên hệ**: thêm mới bằng `ContactFormTable::insertForm()`; form trùng tên với form đã có → hỏi ở màn xem trước: "Bỏ qua" (mặc định) / "Ghi đè" (`updateForm`) / "Thêm bản sao".
5. **Secret** (chỉ khi file có và người dùng tick "Nhập cả mật khẩu/khoá API"): ghi qua đúng luồng lưu của từng tính năng để được mã hoá lại bằng AUTH_KEY site mới (SMTP: `update_option('_smtp_password', plain)` → hook mã hoá sẵn có tự chạy; kiểm lại sau khi ghi là đã `laca:v1:`).
6. **SEO**: gọi `\App\Features\LacaSeo\ImportExport\Importer::importJson($seoPayload, $replaceDomain, $preserveCredentials)`.
7. Sau khi ghi: `DatabaseCleaner` cập nhật lịch cron theo option mới; `flush_rewrite_rules()` 1 lần nếu CPT/login slug/sitemap đổi; xoá transient `laca_security_audit_result`, `laca_storage_health`; `wp_cache_flush()` nếu có object cache.
8. Ghi `ConfigChangeLog::log('site_config_import', '', 'file … — N mục')` (1 dòng tổng, không ghi từng giá trị secret).
9. Màn kết quả: số mục đã ghi theo section, ảnh đã tải, form đã tạo, danh sách **"Cần chọn lại thủ công"** (field trỏ tới trang/bài theo ID, ảnh tải lỗi, secret không nhập), link nhanh tới trang tương ứng; nếu login slug đổi → hiện URL đăng nhập mới thật to.

### 4.3 Khôi phục
- Danh sách bản sao lưu (thời gian, người làm, file nguồn, số mục) + nút "Khôi phục" (confirm) → ghi lại đúng giá trị cũ, `delete_option` với mục trước đó chưa tồn tại, xoá form do lần nhập đó tạo (lưu ID form đã tạo trong bản sao lưu), gọi khôi phục SEO tương ứng của Laca SEO nếu có. Không xoá ảnh đã tải về (chỉ liệt kê để người dùng tự xoá nếu muốn).

---

## 5. Giao diện

- Trang **Laca Admin → Cấu hình**, tab "Xuất / nhập" làm lại thành 3 card theo thứ tự: **Xuất bộ cấu hình** (checkbox section + cảnh báo secret + nút tải) · **Nhập bộ cấu hình** (upload → xem trước → tuỳ chọn → "Áp dụng N thay đổi" danger + confirm) · **Bản sao lưu trước khi nhập** (bảng + Khôi phục). Tab "Nhật ký thay đổi" giữ nguyên.
- Card xuất có 1 dòng mô tả: "Dùng khi dựng site mới: xuất ở site mẫu, nhập ở site mới."
- Trang **Laca SEO → Import/Export**: giữ nguyên chức năng SEO riêng; thêm `laca-alert` info đầu trang: "Muốn sao chép toàn bộ cấu hình site (Laca Admin + Laca SEO) trong 1 file, dùng Laca Admin → Cấu hình →" (link).
- Trang Tổng quan: card "Sao lưu cấu hình" đổi mô tả thành "Xuất/nhập toàn bộ cấu hình (Laca Admin + SEO) để dựng site mới nhanh."
- Đủ trạng thái: đang tải lên, đang áp dụng (khoá nút, hiện tiến độ theo bước: ghi cấu hình → tải ảnh → form → SEO), lỗi từng bước (không dừng cả quá trình nếu 1 ảnh lỗi), rỗng (chưa có bản sao lưu).

---

## 6. Kiểm tra

1. `php -l`, build A + B child, `wp i18n make-pot`, parity `cmp` = 0 khác biệt **sau bước cuối**.
2. Test chính (ghi output thật vào báo cáo):
   - **Hub → client**: xuất toàn bộ ở `lacadev.local` (không secret) → nhập ở `lacadev-client.local`. Kiểm: mọi option trong file (đã thay domain) bằng giá trị trên site đích; các option danh tính của site đích (`_laca_project_id`, `_laca_tracker_endpoint`, `_laca_key_*`, …) KHÔNG đổi (so trước/sau); ảnh logo được tải về và field trỏ ID mới; form mẫu được tạo; SEO có cấu hình như nguồn, thông tin Google của site đích giữ nguyên.
   - **Khôi phục** bản sao lưu vừa tạo → dump option trước nhập và sau khôi phục giống hệt (diff = 0, trừ ảnh đã tải).
   - **Bảo mật nhập**: file chế tạo chứa `siteurl`, `admin_email`, `default_role=administrator`, `users_can_register=1`, `active_plugins`, `_laca_project_id`, `laca_login_guard_locked_ips`, `laca_discord_hub_webhooks` → TẤT CẢ ở "Bị bỏ qua", DB không đổi.
   - File không phải JSON / >5MB / `format` sai / user không phải admin / nonce sai → bị từ chối đúng thông báo.
   - Tương thích: nhập 1 file cũ của `ConfigExportImport` và 1 file JSON của Laca SEO → chạy được.
   - Secret: xuất có secret ở site A, nhập ở site B với tick nhập secret → `_smtp_password` trên B là `laca:v1:` và giải mã ra đúng mật khẩu gốc.
   - Nhập lại cùng file lần 2 → không tải ảnh trùng, form trùng tên bị "Bỏ qua".
3. Hồi quy: mở toàn bộ trang admin 2 site + frontend + `wp-login.php` → không Fatal/Warning mới.
4. **Khôi phục 2 site về trạng thái trước test** bằng chính tính năng khôi phục + xoá form/ảnh do test tạo (ảnh test xoá bằng `wp_delete_attachment` — do test tạo). Ghi rõ đã trả lại những gì.

## 7. Báo cáo

`/tmp/laca-parity/site-config/REPORT.md`: file tạo/sửa; **bảng phân loại đầy đủ mọi option** (Cấu hình / Danh tính / Trạng thái, kèm section); bảng container CF → section; kết quả từng test với output thật; ảnh chụp 1440px + 375px các màn xuất, xem trước, kết quả, sao lưu (`/tmp/laca-parity/site-config/screenshots/`); chỗ lệch plan + lý do. Progress qua agent-bridge sau mỗi mục 2→5 và khi xong.
