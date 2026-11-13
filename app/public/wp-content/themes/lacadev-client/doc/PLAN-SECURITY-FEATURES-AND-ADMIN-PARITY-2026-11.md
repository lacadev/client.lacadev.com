# Plan: Bổ sung tính năng (học từ Sitevorx Pro) + đồng bộ phần quản trị giữa `lacadev` và `lacadev-client` (11/2026)

> Người thực hiện: Antigravity. Người review: Claude Code. Người duyệt: chủ dự án.
> Người dùng đã duyệt mục tiêu. **KHÔNG dùng plugin Sitevorx Pro**, chỉ học ý tưởng, không copy nguyên code của plugin.
> Báo cáo phân tích gốc: `/private/tmp/claude-501/-Users-lacadev-Documents-wordpress/91982f4e-93db-455f-8f6d-483e29770ae0/scratchpad/sitevorx-vs-lacadev-report.md` (có 4 điểm sai đã được đính chính ở mục "Sự thật đã kiểm" bên dưới).

## Mục tiêu
1. **Phần quản trị giống nhau** giữa hub `lacadev` và parent `lacadev-client`: Laca Admin, Laca SEO, Laca Theme (theme options), Dashboard (widget), Bảo mật, Hiệu năng & Bảo trì, Login Log, Email Log, Form Liên hệ, Custom Post Types, Dịch vụ Google, Discord Hub, Exit Popup & Chatbot. Code dùng chung phải **giống hệt nhau** ở 2 theme. Chỉ các module đặc thù vai trò được phép khác (xem bảng ở Phần 1).
2. Vá **2 lỗi bảo mật thật**: SVG upload không lọc, secret 2FA và mật khẩu SMTP lưu plaintext.
3. Thêm tính năng: honeypot đăng nhập độc lập, dọn DB theo lịch, giao diện cho LoginGuard, security headers có cấu hình, gettext cho chuỗi ghi cứng, giới hạn revision/Heartbeat, xuất/nhập cấu hình, nhật ký thay đổi cấu hình.

## Phạm vi project

| Vai trò | Theme | Đường dẫn theme | Site local | Local id (socket) |
| --- | --- | --- | --- | --- |
| Hub (Theme A) | `lacadev` | `~/Documents/wordpress/lacadev/app/public/wp-content/themes/lacadev` | http://lacadev.local | `r_Z9Sldo9` |
| Nguồn parent (Theme B) | `lacadev-client` | `~/Documents/wordpress/lacadev-client/app/public/wp-content/themes/lacadev-client` | http://lacadev-client.local | `RqSXliHDN` |
| Build của Theme B | `lacadev-client-child` | `~/Documents/wordpress/lacadev-client/app/public/wp-content/themes/lacadev-client-child` | | |

- **CHỈ sửa 2 theme `lacadev` và `lacadev-client` (bản nguồn) ở trên.** KHÔNG sửa `jackacademy`, `lixroastery`, `phucdainam`, `nhakhoathienphuoc`. Các site này sẽ nhận code qua quy trình ở `doc/PLAN-PARENT-SYNC-AND-UPDATE-2026-11.md` (Phần 3), làm sau.
- Theme B không có `package.json`: SCSS/JS admin của parent được build từ `lacadev-client-child` (webpack import `../../../lacadev-client/resources`). Theme A tự build trong thư mục của nó.
- PHP CLI: `~/Library/Application Support/Local/lightning-services/php-8.3.17+1/bin/darwin-arm64/bin/php`, socket `~/Library/Application Support/Local/run/<id>/mysql/mysqld.sock`. Cách nạp WP để test xem skill `lacadev-theme` mục 4 (`~/.claude/skills/lacadev-theme/SKILL.md`). **Đọc skill đó trước khi code**, đặc biệt mục 1.5, 3, 4, 5.

## Quy tắc an toàn (bắt buộc)
- Backup trước khi sửa: `tar` 2 thư mục theme (bỏ `node_modules`, `vendor`) vào `/tmp/laca-parity/backup/<theme>-<timestamp>.tar.gz`.
- Ghi lại `git status --short` của 2 repo trước khi làm. **Không đụng các file đang dirty có sẵn** (`dist/*` của lacadev, `releases/`, `tools/`, `theme-updates/`…).
- Không `rm`, không `git reset/checkout -- ./clean/stash drop`. **Không commit, không push, không chạy `build-release.sh`.**
- Không sửa plugin (kể cả Sitevorx Pro), WP core, `wp-config.php`. Không deactivate plugin. Nếu Sitevorx đang bật trên lacadev.local và làm sai kết quả test (SMTP, login), **dừng lại và hỏi** người dùng.
- `composer require` **chỉ được dùng cho đúng 1 package** `enshrined/svg-sanitize` (đã duyệt) trong 2 theme. Không thêm, nâng hay gỡ package nào khác. Không `yarn add`.
- DB: chỉ thao tác trên local. Không `wp search-replace`, không xoá hàng loạt (trừ khi chính tính năng dọn DB đang được test, và chỉ trên local).
- i18n theo `~/.claude/CLAUDE.md`: mọi câu chữ đi qua `__()`/`esc_html__()`… với text domain `laca`, msgid tiếng Việt, câu có biến dùng `sprintf` + `/* translators: */`. JS không ghi cứng chữ.
- Chuẩn code theo skill `lacadev-theme` mục 1.5: escape output, nonce + `current_user_can('manage_options')` cho mọi AJAX/form, sanitize input, `$wpdb->prepare`, option không cần autoload thì `autoload = false`.
- Mỗi lần bắt đầu hoặc xong một phần, gửi `progress` qua agent-bridge cho Claude, kèm danh sách file đang sửa.

## Sự thật đã kiểm (đính chính báo cáo gốc)
- `lacadev` **đã có** security headers + CSP (`theme/setup/security.php:40`). `lacadev-client` **chưa có**.
- Honeypot đăng nhập **đã có** (`theme/setup/recaptcha.php`, field `laca_hp_email`) nhưng chỉ chạy khi bật reCAPTCHA login.
- Cảnh báo đăng nhập **đã có** qua Discord (`Features/DiscordHub/Monitors/LoginMonitor.php`). Không làm lại.
- `DatabaseCleaner` **đã có** ở cả 2 theme (`Settings/LacaTools/Management/DatabaseCleaner.php`) nhưng chỉ chạy tay.
- Chuỗi ghi cứng trong `wp_send_json_*` (thư mục `app/src/Settings`): A có 23, B có 25.
- `AdminSettings.php:71` (A) và `:75` (B) cho phép upload `svg` mà **không lọc** → lỗi Stored XSS.
- `TwoFactorAuth.php:336` lưu `laca_2fa_secret` dạng plaintext. `ThemeSettings.php` đọc `_smtp_password` plaintext.
- `App\Helpers\Crypto` có ở cả 2 theme (chỉ khác comment và format). `isEncrypted()` dựa vào heuristic base64. **Secret TOTP base32 cũng là chuỗi base64 hợp lệ**, nên KHÔNG được dùng `isEncrypted()` để phân biệt giá trị cũ/mới.

---

## Phần 0 — Chuẩn bị
1. Backup và ghi `git status` như quy tắc.
2. Đọc: skill `lacadev-theme`; `doc/LACADEV_SIMILAR_BUGS_AUDIT.md` và `doc/AI_CODING_INSTRUCTIONS.md` của theme B; 2 plan UI admin có sẵn trong `doc/` của theme B (`ADMIN-UI-CHAKRA-PLAN.md`, `ADMIN-UI-REVISE-PLAN.md`) để không làm ngược hướng.
3. Kiểm cả 2 site local đang chạy, wp-admin mở được. Chụp màn hình các trang admin chính làm mốc "trước".

## Phần 1 — Kiểm kê và đồng bộ phần quản trị (CÓ ĐIỂM DỪNG)

### 1.1 Phân loại module
Đã biết từ `diff -rq app/` (kiểm lại và bổ sung nếu thiếu):

**Chỉ ở hub A (giữ nguyên, không đưa sang B):** `Databases/{AbstractTable,DB,DbVersionManager,Project*Table,UptimeLogTable}`, `Models/Project*`, `PostTypes/{project,service,template}`, `Features/ProjectManagement`, `Features/MobileStickyCta.php`, `Settings/LacaTools/{BlockCatalogHub,ClientPortalEndpoint,ClientRequestHandler,ProjectNotificationHandler,ProjectPdfExporter,ProjectReportsManager,ProjectTrackerGenerator,SiteHealthChecker,ThemePackageEndpoint,TrackerEndpointHandler}`, `Contracts/`, `Module/`, `Notifications/`.

**Chỉ ở client B (giữ nguyên, không đưa sang A):** `Features/{ClientSupport,PostAudio}`, `Security/ClientRoleManager.php`, `Settings/{BlockAutoloader,BlockCatalogProvider,BlockMarketplace,BlockSyncReceiver,FakerData,LacaDevTrackerClient,LayoutModeMeta,ThemeUpdater}.php`, `Widgets/BlockSyncWidget.php`, `helpers/layout-mode.php`.

**Dùng chung nhưng đang khác nhau (cần đồng bộ):** khoảng 50 file, gồm `app/helpers/*`, `app/hooks.php`, `Settings/AdminSettings.php`, `Settings/ThemeSettings.php`, `Settings/LacaAdmin/*`, `Settings/LacaTools/{Optimize,Security,ManagementExperience,AI*}.php`, `Settings/LacaTools/Management/*`, `Settings/Security/*`, `Settings/{EmailLog,LoginLog}/*`, `Settings/MaintenanceModeManager.php`, `Features/{ContactForm,DynamicCPT,ExitIntentPopup,GoogleServices/EntityStats,LacaSeo/Admin/ListScreens/PostList,DiscordHub/Monitors/CronHealthMonitor}`, `Helpers/Crypto.php`, `PostTypes/Concerns/*`, `Abstracts/AbstractPostType.php`, `Databases/ContactFormTable.php`. Thêm phần admin trong `theme/setup/{admin-ui,theme-options,security,performance,recaptcha,users/auth}.php` và `resources/{scripts,styles}/admin/*`.
Phần frontend (template, block, script theme) **nằm ngoài phạm vi**.

### 1.2 Bảng quyết định cho từng file dùng chung
Với mỗi file khác nhau, chạy `diff -u A B` rồi xếp vào 1 trong 4 loại:
- **(a) chỉ khác format/comment**: lấy bản đúng chuẩn hơn, chép sang bên kia.
- **(b) một bên mới hơn, bên kia không có thay đổi riêng** (xem `git log -p` của file ở cả 2 repo): lấy bên mới hơn.
- **(c) cả 2 bên có thay đổi riêng có giá trị**: gộp tay để có bản chung chứa cả hai.
- **(d) khác nhau vì vai trò hub/client** (vd menu chỉ hub có, gọi class chỉ một bên có): chuyển phần khác biệt ra **điểm mở rộng** dùng chung, rồi để module đặc thù vai trò tự gắn vào. Không để `if (is_hub)` rải khắp file. Ưu tiên dùng `class_exists()` cho class chỉ một bên có, hoặc `apply_filters`/`do_action` đặt tên `laca_admin_*`.

Ghi bảng vào báo cáo: `file | loại | bên lấy làm gốc | tóm tắt khác biệt | rủi ro`.

### 1.3 ĐIỂM DỪNG
Sau khi có bảng 1.2 (chưa sửa file nào), **dừng lại, gửi báo cáo cho Claude** kèm `diff` đầy đủ của các file loại (c) và (d). Chỉ làm 1.4 khi Claude trả lời "tiếp tục".

### 1.4 Áp dụng đồng bộ
- Áp từng nhóm (LacaAdmin → Security → LacaTools → còn lại), mỗi nhóm xong thì `php -l` ngay.
- Kết quả: mọi file dùng chung có `diff` = 0 giữa A và B, trừ những chỗ đã ghi rõ trong bảng là loại (d).
- Viết script `tools/parity-check.sh` để **tạo trong `/tmp/laca-parity/`, không đặt trong repo**. Script in danh sách file dùng chung còn khác, để Claude chạy lại khi review.

## Phần 2 — Vá bảo mật (P0)
Mọi code dưới đây viết **một lần, giống hệt nhau** ở A và B.

### 2.1 Lọc SVG khi upload
- `composer require enshrined/svg-sanitize` trong 2 theme. Kiểm `build-release.sh` của B vẫn đóng gói được `vendor/`.
- Class mới `App\Settings\Security\SvgUploadGuard` (đăng ký ở nơi các class Security khác đang được khởi tạo):
  - `upload_mimes`: chỉ thêm `svg` cho user có `unsafe_filtered_html`, còn lại gỡ `svg` khỏi danh sách.
  - `wp_handle_upload_prefilter`: nhận diện SVG theo **cả extension lẫn MIME** (bắt cả `.svgz`). Chạy cho **mọi user** (plugin chỉ chạy cho admin, đó là lỗi, không làm theo). Sanitize bằng `enshrined\svgSanitize\Sanitizer` với `removeRemoteReferences(true)`. Kết quả `false` hoặc rỗng thì chặn và báo lỗi qua gettext. Ghi đè file tạm bằng nội dung đã lọc.
  - `wp_check_filetype_and_ext`: sửa nhận diện SVG cho đúng, để WP không chặn nhầm file hợp lệ.
- Bỏ `'svg' => 'image/svg+xml'` khỏi `addCustomExtensionsInMediaUpload()` ở `AdminSettings` (2 theme).
- Test bằng PHP CLI với 4 file SVG mẫu (tạo trong `/tmp/laca-parity/svg/`): icon sạch → giữ nguyên hình; có `<script>`; có `onload=`; có `<foreignObject>` + `xlink:href="javascript:"`. 3 file độc phải được lọc sạch hoặc bị chặn. Thử thêm upload bằng tài khoản Editor tạo tạm (xoá user sau khi test).

### 2.2 Mã hoá secret 2FA và mật khẩu SMTP
- Đồng bộ `App\Helpers\Crypto` giữa 2 theme trước (thuộc Phần 1). **Không đổi** `encrypt()/decrypt()` hiện có, vì `HasEncryption` của CPT project đang dùng.
- Thêm vào `Crypto`: `encryptTagged(string): string` trả `'laca:v1:' . encrypt(...)`, `decryptTagged(string): ?string` trả `null` khi giải mã thất bại, và `isTagged(string): bool` (chỉ kiểm prefix). Giá trị chưa có prefix được coi là plaintext cũ.
- **2FA (`TwoFactorAuth`)**: thêm 1 hàm đọc secret dùng chung cho mọi chỗ đang `get_user_meta(..., 'laca_2fa_secret')` (grep hết). Nếu chưa có prefix: dùng nguyên giá trị rồi **lưu lại bản mã hoá** (tự chuyển dần). Mọi chỗ ghi đều dùng `encryptTagged`.
  - Giải mã thất bại (vd đổi `AUTH_KEY` khi chuyển host): **KHÔNG được bỏ qua bước 2FA.** Báo lỗi đăng nhập, gợi ý dùng mã dự phòng hoặc nhờ Super User reset 2FA. Kiểm luồng reset hiện có vẫn chạy được.
  - Kiểm cách lưu backup codes: nếu đang lưu plaintext thì chuyển sang `wp_hash_password` hoặc hash, và giữ tương thích ngược.
- **SMTP**: theo đúng pattern `PostTypes/Concerns/HasEncryption.php` (load thì decrypt, save thì encrypt) cho field Carbon Fields `smtp_password`. Đổi field sang `->set_attribute('type', 'password')`. `ThemeSettings` decrypt trước khi gán `$phpmailer->Password`. Giải mã thất bại thì hiện admin notice yêu cầu nhập lại mật khẩu SMTP (gettext).
- Test PHP CLI: user có secret plaintext cũ vẫn xác thực TOTP được, sau lần đọc đầu thì meta có prefix; tạo secret mới thì có prefix; giải mã hỏng thì không vào được; SMTP lưu ra có prefix và gửi mail test qua `wp_mail` chạy được (xem log Email Log).

## Phần 3 — Tính năng mới (P1)

### 3.1 Honeypot đăng nhập độc lập
- Tách honeypot đăng nhập khỏi `recaptcha.php` thành class `App\Settings\Security\LoginHoneypot`, bật mặc định. Có option bật/tắt `laca_login_honeypot` trong tab Bảo mật.
- Field ẩn + bẫy thời gian (submit nhanh hơn 2 giây là bot). Kiểm ở `authenticate` priority 1, chỉ khi request là form `wp-login.php` có field `log`, giống điều kiện đang có ở `verify_login`.
- reCAPTCHA vẫn giữ honeypot của nó cho register/comment/contact. **Không được in 2 field trùng tên** trên form login.
- Test: đăng nhập thường (cả slug đăng nhập tuỳ chỉnh và luồng 2FA), quên mật khẩu, đăng ký đều chạy bình thường. POST có điền honeypot thì bị chặn, và LoginGuard ghi nhận lần thất bại.

### 3.2 Dọn database theo lịch (mở rộng `DatabaseCleaner`)
- Thêm phần cấu hình: bật/tắt (**mặc định TẮT**), tần suất (hàng ngày/hàng tuần), tick chọn hạng mục. Hạng mục: revision (giữ N bản mới nhất mỗi bài, mặc định 5), auto-draft cũ hơn 7 ngày, bài trong thùng rác quá 30 ngày, comment spam/trash, transient hết hạn (`delete_expired_transients(true)`), postmeta mồ côi.
- Xoá theo batch 500 dòng bằng `$wpdb->prepare`, nghỉ 100ms giữa các batch. Khoá chống chạy chồng bằng transient. Ghi kết quả lần chạy gần nhất vào option `autoload = false` và hiện trên giao diện.
- Cron đăng ký/huỷ theo option. Huỷ cron ở `switch_theme`.
- Test trên local: tạo dữ liệu rác mẫu, chạy hàm cron trực tiếp, kiểm số dòng bị xoá đúng và bài thật không bị đụng.

### 3.3 Giao diện cho LoginGuard
- Option `laca_login_guard_settings` gồm: số lần sai tối đa, cửa sổ thời gian, thời gian khoá, ngưỡng leo thang strike, danh sách IP được miễn (validate bằng `filter_var(FILTER_VALIDATE_IP)`). `LoginGuard::config()` đọc option rồi vẫn cho filter cũ `laca_login_guard_settings`/`laca_login_guard_whitelist` chạy sau (giữ tương thích).
- Danh sách IP đang bị khoá + nút "Mở khoá" (dùng `LoginGuard::unlock()`). Nếu hiện tại không liệt kê được IP bị khoá (do lưu bằng transient), thêm 1 option index nhỏ `autoload = false` và dọn mục hết hạn.
- Đặt trong tab mới "Chống dò mật khẩu" ở trang Bảo mật. Quyền giống các tab Bảo mật khác (kiểm cả Super User, xem skill mục 3).

### 3.4 Security headers có cấu hình (cả 2 theme)
- Chuyển code headers trong `theme/setup/security.php` (A) thành class dùng chung `App\Settings\Security\SecurityHeaders`, có toggle cho: X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, HSTS (chỉ gửi khi `is_ssl()`, có max-age và includeSubDomains), CSP.
- **[Sửa 11/11]** Cả 2 theme ĐỀU đang gửi headers + CSP (B còn có HSTS, nonce, filter `laca_csp_frame_src`). Mặc định của toggle **phải giữ đúng hành vi hiện tại** của bản đã merge ở Phần 1: header cơ bản BẬT, CSP BẬT, HSTS như B (chỉ gửi khi `is_ssl()`). Không cần migrate riêng cho hub. Bỏ `X-XSS-Protection` (đã lỗi thời).
- Kiểm iframe/embed và script Google ở frontend 2 site không bị chặn (xem console).

### 3.5 gettext cho chuỗi ghi cứng
- Rà toàn bộ `app/src/Settings`, `app/src/Features`, `theme/setup` ở 2 theme: `wp_send_json_*`, `wp_die`, admin notice, `aria-label`/`placeholder`/`title`. Bọc gettext, domain `laca`.
- Cập nhật `.pot` (`wp i18n make-pot`). Nếu có `.po` cho locale khác thì thêm bản dịch rồi `wp i18n make-mo`. Không có công cụ `wp` thì ghi rõ trong báo cáo, không bỏ qua im lặng.

## Phần 4 — Tính năng P2
- **4.1 Giới hạn revision + Heartbeat** trong "Hiệu năng & Bảo trì" (`LacaTools/Optimize.php`): số revision giữ lại (filter `wp_revisions_to_keep`, mặc định không đổi hành vi WP); Heartbeat với 3 lựa chọn: mặc định / giảm tần suất (60s) / tắt ngoài trang soạn bài.
- **4.2 Xuất/nhập cấu hình** (Laca Admin → Cấu hình chung, tab "Sao lưu cấu hình"):
  - Xuất JSON các option theme (`laca_*` và option Carbon Fields của theme options). **Loại bỏ mọi secret**: mật khẩu SMTP, API key, token, client secret, khoá HMAC, secret 2FA. Dùng danh sách chặn theo tên + regex `(pass|secret|token|key|hmac)`.
  - Nhập: nonce + `manage_options`. Chỉ nhận key nằm trong whitelist. Xem trước diff rồi mới áp dụng. Ghi vào nhật ký 4.3.
- **4.3 Nhật ký thay đổi cấu hình**: hook `updated_option`/`added_option` cho các option thuộc theme (cùng whitelist 4.2). Ghi user, IP, thời gian, tên option, và chỉ ghi giá trị cũ/mới khi **không phải secret**. Lưu trong option dạng vòng 200 mục, `autoload = false`. Hiển thị trong tab mới ở Bảo mật, có nút xoá (nonce).

## Phần 5 — Giao diện quản trị
- **Antigravity không tự thiết kế lại giao diện trong plan này.** Màn hình mới (3.2, 3.3, 3.4, 4.x) dùng lại đúng component đang có: thanh tab dạng pill như trang Bảo mật, `_tokens.scss` và các biến `--laca-*`, card/table hiện có. Không thêm màu, font hay ngôn ngữ thiết kế mới.
- Phần nâng cấp giao diện (trang tổng quan bảo mật có điểm số và mục cần xử lý, toggle thay checkbox, gom card) sẽ do **Claude làm wireframe với người dùng theo `evon:ui-ux`**, xong mới giao Antigravity dựng. Trong báo cáo, chỉ liệt kê màn hình nào hiện dùng checkbox dài hoặc chưa đồng nhất.

## Phần 6 — Build và kiểm tra
1. `php -l` mọi file PHP đã sửa hoặc thêm ở 2 theme (in số file và kết quả).
2. Theme A: `yarn build` rồi `yarn lint` trong `lacadev`. Theme B: `yarn build` rồi `yarn lint` trong `lacadev-client-child` của project `lacadev-client`. Lint 0 error. Lỗi format thì chạy `lint-fix` trước. Không để watcher chạy nền.
3. `vendor/bin/phpstan` nếu có, so với baseline. Lỗi mới thì sửa.
4. Chạy lại `parity-check.sh`: file dùng chung chỉ còn khác ở những chỗ loại (d) đã ghi.
5. Kiểm bằng trình duyệt ở cả lacadev.local và lacadev-client.local: Dashboard, Laca Admin (mọi tab), Laca SEO, Laca Theme, Bảo mật (mọi tab), Hiệu năng & Bảo trì, Login Log, Email Log, 2FA. Yêu cầu: không fatal, không lỗi console mới, không lỗi PHP trong `debug.log`. Chụp màn hình "sau".
6. Test đầu-cuối: đăng nhập có 2FA, đổi slug đăng nhập, khoá IP rồi mở khoá, upload SVG (admin và editor), gửi mail SMTP, xuất rồi nhập lại cấu hình trên cùng site.
7. Dọn mọi user, option và dữ liệu test đã tạo trên local.

## Báo cáo (gửi Claude qua agent-bridge + lưu file)
Lưu tại `/tmp/laca-parity/REPORT.md`, gồm:
- Bảng Phần 1.2 và trạng thái áp dụng.
- Danh sách file đã sửa/thêm theo từng theme, mỗi file 1 dòng mô tả.
- Output thật của `php -l`, `yarn lint`, `yarn build`, phpstan và các test PHP CLI. **Lỗi thì dán nguyên văn, không tóm tắt cho đẹp.**
- Mục chưa làm được và lý do; quyết định đã tự đưa ra; câu hỏi cần người dùng trả lời.
- Ghi chú phát hành: thay đổi nào cần `composer install` khi đồng bộ sang các site client; option nào sẽ đổi mặc định trên production (đặc biệt CSP ở hub, honeypot bật mặc định).
- Đường dẫn ảnh chụp trước/sau.
