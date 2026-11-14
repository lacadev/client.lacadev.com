# Nhật ký thay đổi (Changelog)

## [3.9.1] - 2026-11-14

- **Nâng cấp mật mã AEAD (`Crypto` v2 & `GscCrypto` v2)**: Chuyển toàn bộ mã hoá dữ liệu nhạy cảm từ AES-256-CBC không MAC sang `sodium_crypto_secretbox` (`laca:v2:` / `laca:gsc:v2:`), dẫn xuất khoá bằng HKDF-SHA256 từ `AUTH_KEY` + `SECURE_AUTH_SALT` (hoặc hằng `LACA_CRYPTO_KEY` base64 32 byte) và từ chối mã hoá khi thiếu khoá hợp lệ. **Ghi chú vận hành**: cơ chế migration lười (lazy rehash) tự động nâng cấp dữ liệu cũ ngay khi đọc/lưu trên production; để chuyển đổi chủ động toàn bộ DB một lần, chạy `wp laca crypto migrate [--dry-run]`. **Cảnh báo quan trọng**: việc xoay/đổi `AUTH_KEY` hoặc `SECURE_AUTH_SALT` trong `wp-config.php` sẽ làm mất khả năng giải mã toàn bộ dữ liệu đã mã hoá — khuyến nghị khai báo hằng `LACA_CRYPTO_KEY` cố định (32 byte ngẫu nhiên mã hoá base64) trong `wp-config.php`.
- **Snapshot không pop-stack & hoàn tác `pre-rollback`**: `ThemeSnapshot::restore()` giữ nguyên bản snapshot sau khi khôi phục (thay cho hành vi pop-stack ở 3.9), tự động chụp bản đang chạy thành loại `pre-rollback` trước khi ghi đè, chọn bản `update` mới nhất cũ hơn phiên bản hiện tại khi gọi mặc định (`laca_snapshot_no_older` nếu hết bản cũ hơn), giữ tối đa 3 bản `update` + 2 bản `pre-rollback`, và hiển thị cột `type` trong `wp laca-theme snapshots`.
- **Tôn trọng `category` của Gutenberg Block trong bucket**: `gutenberg-blocks.php` ưu tiên trường `category` khai báo trong `block.json` cho cả block đặt trong thư mục `*-blocks-site/`, chỉ rơi về tên bucket khi `block.json` không khai báo `category`.
- **AJAX Handler `laca_footer_contact_submit`**: Bổ sung `FooterContactHandler` xử lý form liên hệ chân trang và popup dự án (kiểm tra nonce `laca_footer_contact_nonce`, giới hạn tần suất 5 lần/10 phút theo IP, kiểm tra định dạng SĐT Việt Nam, tự khởi tạo form trong `ContactFormTable`, lưu submission và gửi email thông báo; khi bản ghi đã lưu DB thành công nhưng gửi email gặp sự cố, hệ thống ghi `error_log` và vẫn trả phản hồi thành công 200 — khác có chủ đích với `ContactFormAjaxHandler` — nhằm ưu tiên không làm gián đoạn trải nghiệm thu lead và tránh khách gửi lặp gây trùng dữ liệu), đồng thời chuyển câu chữ dự phòng JS sang `data-msg-*` đa ngôn ngữ và sửa lỗi DOM clobbering `form.getAttribute('action')`.

## 3.9 (13/11/2026)

- **Parent tự mang asset riêng (`dist/`)**: Bundle `admin`, `login`, `editor`, `laca-seo`, `laca-seo-editor` và `vendors` đi kèm gói Parent Theme với cache-busting `filemtime`. Từ 3.9 parent tự mang asset admin/login/SEO; entry tương ứng ở child sẽ gỡ ở 4.0.
- **Đóng gói tất định & xác minh 2 lớp**: Phát hành qua `tools/release/build-package.sh` (`.distignore`, `composer install --no-dev`), ký manifest bằng Ed25519 (`ReleaseVerifier`) và kiểm tra SHA-256 trước khi cài đặt.
- **Snapshot, Rollback & Báo cáo Fleet**: Tự động chụp snapshot Parent Theme trước khi nâng cấp, hỗ trợ khôi phục qua WP-CLI (`wp laca-theme rollback`) hoặc từ xa (`rollback_theme`), gửi trạng thái `theme_update_result` về Hub và cảnh báo khi Child Theme cần build lại (`ChildBuildNotice`). *(Ghi chú pop-stack ban đầu đã được thay thế từ bản `[3.9.1]` bằng cơ chế giữ nguyên snapshot kèm bản sao lưu `pre-rollback`.)*
- **Đồng bộ Block chọn lọc an toàn**: Xác thực HMAC-SHA256, allowlist đuôi file, kiểm checksum từng file, kiểm tra dependency (`parent`, `functions`, `ajax`), chống ghi đè tuỳ biến cục bộ (`409 local_modified`) và hỗ trợ snapshot/rollback từng block.
- **Gia cố bảo mật & quản trị hệ thống**: Hoàn thiện bộ công cụ xuất/nhập cấu hình (`SiteConfig`), đồng bộ môi trường (`LacaSyncCommand`), kiểm soát đăng nhập, khử trùng SVG (`enshrined/svg-sanitize`), khôi phục kiểm tra nonce đăng nhập/đăng ký và loại bỏ DOM XSS trang cấu hình.

## 3.8 (10/11/2026)

- **Cập nhật theme lồng cấp & bảo mật kênh tải**: Sửa lỗi cập nhật cấu trúc lồng cấp `lacadev-client/theme`, sao lưu nguyên thư mục gốc khi nâng cấp, bổ sung ký số HMAC-SHA256 khi tải gói và gửi tracker log chuẩn JSON.
- **Nâng cấp trải nghiệm quản trị**: Đồng bộ cải tiến AI, tối ưu giao diện Laca Admin UX và chính sách CSP.

## 3.7 (09/11/2026)
- **Trang đăng nhập theo giờ**: Nền phong cảnh núi đồi nhiều lớp, rừng thông, mây trôi, lều và lửa trại; mặt trời/mặt trăng đi theo vòng cung theo múi giờ của site, có 4 tông màu bình minh / ngày / hoàng hôn / đêm.
- **Form đăng nhập đổi theo ngày/đêm**: Card kính sáng ban ngày, kính tối buổi tối (logo trắng), màu nhấn xanh rừng / vàng lửa trại; chữ đủ tương phản, card gọn hơn.
- **Câu chữ trang đăng nhập dịch qua file .po**: Không còn ghi cứng tiếng Việt/Anh trong JS; ô ghi đè trong Theme Options vẫn được ưu tiên.
- **Lưu ý cập nhật**: Nền mới chỉ hiện sau khi build lại theme con (`yarn build`); site chưa build vẫn giữ nền sao cũ.

## 3.6 (09/11/2026)
- **Dashboard mới**: Khối "Bảng điều khiển" ở đầu trang gồm số liệu (bài viết, trang, form liên hệ chưa đọc, bảo trì), Việc cần làm và Thao tác nhanh; bỏ banner Welcome, widget Site Health tiếng Anh và các ô trống "Drag boxes here".
- **Thanh lưu dính đáy**: Các trang cài đặt (Laca Admin, Laca Theme, Tối ưu hóa…) bỏ card "Actions" bên phải; nút Lưu và trạng thái tự lưu nằm ở thanh dưới cùng.
- **Laca Theme dùng tab dọc**: 10 tab hiện hết một lúc thay vì xuống 2 dòng.
- **Danh sách bài viết**: Gộp 3 cột SEO thành 1 (điểm + tình trạng tiêu đề/mô tả + nút "Sửa SEO" mở Quick Edit); bảng hết cuộn ngang.
- **Kiểm tra bảo mật**: Mục chưa đạt ghi kết quả thật lên trước, kèm tiêu chí và cách sửa; điểm ghi 0/n cho mục chưa đạt.
- **Gọn hơn**: Bỏ emoji ở tiêu đề trang và widget; hộp mô tả lặp tiêu đề rút thành 1 dòng.
- **Admin tiếng Việt mặc định**: User chưa tự chọn ngôn ngữ thấy admin tiếng Việt khi site có gói ngôn ngữ vi (không đổi ngôn ngữ frontend).

## 3.5 (09/11/2026)
- **Giao diện Laca Admin gọn hơn**: Dock gom còn 6 nhóm (không còn nhóm chỉ 1 mục), checklist phát hành thu gọn mặc định, thanh tab con có tiêu đề nhóm ở trên, trang Tối ưu hóa dùng nhãn tiếng Việt.
- **Màu trạng thái**: Đạt / cảnh báo / lỗi có màu riêng (xanh lá / cam / đỏ) thay vì cùng tông đen; vòng điểm kiểm tra bảo mật hiển thị đúng.
- **Sửa lỗi hiển thị**: Chân trang không còn bị dock che; bỏ cột Categories bị trùng và ẩn sẵn cột ít dùng ở danh sách bài viết; logo widget "Giới thiệu" và admin bar không còn bị vỡ.
- **Hướng dẫn**: Gộp "Hướng dẫn" vào menu "HD Sử dụng"; tài khoản Khách hàng mở được trang HD Sử dụng.

## 3.4 (07/11/2026)
- **Menu "Quản lý người dùng"**: Xác thực 2 bước (2FA) và Login Log (nhật ký đăng nhập thất bại) chuyển thành trang riêng trong nhóm Quản lý người dùng của Laca Admin. Login Log trước đây đã ghi log nhưng bị ẩn khỏi menu.
- **Menu "Super User" riêng**: Quản lý Super User tách khỏi trang Bảo mật thành menu độc lập. Link cũ tới tab 2FA/Super User tự chuyển sang trang mới.
- **Yêu cầu hỗ trợ**: Chỉ người dùng có quyền sửa bài viết mới thấy nút và widget gửi yêu cầu hỗ trợ.

## 3.3 (03/11/2026)
- **Trung tâm hỗ trợ khách hàng**: Widget dashboard và nút truy cập nhanh trên admin bar cho phép khách gửi yêu cầu hỗ trợ (lỗi, nội dung, tính năng...) có ký số HMAC-SHA256, đính kèm ảnh chụp màn hình từ Media Library và theo dõi trực tiếp trạng thái phản hồi từ kỹ thuật viên.
- **Báo cáo vận hành hàng tháng**: Tự động tổng hợp số liệu bài viết, trang mới, sự kiện bảo mật và dữ liệu hiệu suất Google Analytics 4 / Search Console định kỳ gửi về trung tâm điều hành.
- **Vai trò "Khách hàng" (`laca_client`)**: Bổ sung vai trò phân quyền an toàn cho khách tự quản lý nội dung (bài viết, trang, media, menu, form, SEO) và tự động ẩn các khu vực kỹ thuật nhạy cảm (plugins, themes, users, tools, cài đặt hệ thống).
- **Trang hướng dẫn sử dụng trực quan**: Tích hợp trang tài liệu ngắn gọn cho khách ngay trong menu quản trị.
- **Giám sát sao lưu & Cập nhật plugin**: Tự động nhận diện sao lưu của các plugin phổ biến (UpdraftPlus, All-in-One WP Migration, v.v.) và ghi nhận nhật ký cập nhật plugin trong heartbeat gửi về Hub.

## 3.2 (03/11/2026)
- **Bảo mật & Xác thực đa tầng**: Tách riêng 4 API key theo mục đích sử dụng (Tracker, Catalog, Remote, Portal) và hỗ trợ xác thực chữ ký số HMAC-SHA256 chống giả mạo, replay attack.
- **Kênh cập nhật an toàn**: Tải gói theme cập nhật qua endpoint bảo vệ tại trung tâm điều hành `lacadev.com` thay vì link công khai.
- **Chuẩn hoá kết nối**: Tối ưu hoá đường dẫn canonical REST API (`/wp-json/*`), tăng tính ổn định khi đồng bộ block và cập nhật từ xa.
- **Tương thích ngược**: Hỗ trợ chuyển tiếp mượt mà cho các site khách chưa nâng cấp key mới qua cơ chế Legacy Authentication.

## 3.1 (12/10/2026)
- Cập nhật cơ chế đồng bộ Block Gutenberg và ghi nhận hoạt động tự động.
- Nâng cấp giao diện quản trị Laca Admin Settings.
