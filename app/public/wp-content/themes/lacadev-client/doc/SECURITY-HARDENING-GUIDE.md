# Hướng Dẫn Gia Cố Bảo Mật & Chính Sách Đăng Nhập (Security Hardening Guide)

Tài liệu hướng dẫn quản trị viên vận hành các cơ chế bảo mật nâng cao được tích hợp trong giao diện quản trị **Laca Admin → Bảo mật**.

---

## 1. Tổng quan & Vị trí cấu hình

Tất cả các thiết lập bảo mật nằm tại:
- **Trang quản trị**: `Laca Admin` → `Bảo mật` (URL: `admin.php?page=laca-security`)
- **Tab chính**:
  - `Đăng nhập`: Quản lý khoá IP, giới hạn đăng nhập sai, whitelist IP, chống bot Honeypot, Chính sách đăng nhập nghiêm ngặt và Thông báo Discord.
  - `Gia cố`: Công tắc các security headers (X-Frame-Options, X-Content-Type-Options, HSTS, CSP, Referrer-Policy) và nút kiểm tra header trực tiếp.
  - `Kiểm tra bảo mật`: Bảng kiểm toán tự động các nguy cơ bảo mật hệ thống (file nhạy cảm, update core/plugin/theme, username cấm, 2FA admin, Application Passwords, HIBP).

---

## 2. Chi tiết các tính năng trong Chính sách đăng nhập

Nằm tại Card **Chính sách đăng nhập** (tab Đăng nhập):

| Tính năng | Mô tả & Cách hoạt động | Giá trị mặc định | Rủi ro tự khoá |
| :--- | :--- | :--- | :--- |
| **Khoá IP khi username không tồn tại** | Nếu username gửi lên form đăng nhập không có trong DB WordPress, IP gửi request sẽ bị khoá ngay ở lần thử đầu tiên. Ngăn chặn triệt để tấn công brute-force dò từ điển username. | **Tắt (off)** | **Cao**: Nếu quản trị viên hoặc người dùng gõ sai username của chính mình sẽ bị khoá IP ngay. **Khuyến nghị**: Luôn thêm IP cá nhân/văn phòng vào ô **IP luôn được phép** trước khi bật. |
| **Danh sách username cấm dùng** | Danh sách các tên đăng nhập nhạy cảm (vd: `admin`, `administrator`, `root`, `test`, `demo`). Đăng nhập bằng tên này sẽ bị khoá IP lập tức. Người dùng mới cũng không thể đăng ký hoặc tạo tài khoản với các tên này. | `admin`, `administrator`, `root`, `test`, `user`, `demo` | Thấp (trừ khi admin cũ vẫn dùng tên `admin`). |
| **Bắt buộc 2FA theo vai trò** | Ép buộc người dùng thuộc các vai trò được chọn phải kích hoạt xác thực 2 bước (TOTP). | Không chọn vai trò nào | Trung bình: Tài khoản chưa cài 2FA sau hạn sẽ bị buộc chuyển hướng sang trang hồ sơ để quét mã QR cài đặt. |
| **Thời gian ân hạn 2FA (ngày)** | Số ngày tính từ lúc tạo tài khoản mà người dùng được phép đăng nhập bình thường trước khi bị bắt buộc cài đặt 2FA (0 = bắt buộc ngay). | `7` ngày | Không có. |
| **Giới hạn yêu cầu Quên mật khẩu** | Giới hạn số lần một IP có thể gửi yêu cầu reset password trong 1 giờ. Ngăn chặn bot spam làm cạn kiệt tài nguyên gửi email của server. | `5` lần/giờ/IP (0 = tắt) | Thấp: Người dùng bình thường hiếm khi reset quá 5 lần/giờ. |
| **Tắt Application Passwords** | Vô hiệu hoá hoàn toàn tính năng Mật khẩu ứng dụng (Application Passwords) của WordPress core, đóng bề mặt tấn công qua REST API. | **Tắt (off)** | Không có nếu không dùng mobile app bên thứ ba kết nối REST API. |
| **Kiểm tra mật khẩu bị lộ (HIBP)** | Tích hợp k-Anonymity API của Have I Been Pwned. Chỉ gửi 5 ký tự đầu của hash SHA-1 (không gửi mật khẩu thật ra ngoài). Chặn đặt/đổi mật khẩu nếu mật khẩu đã nằm trong dữ liệu rò rỉ; cảnh báo đổi mật khẩu nếu phát hiện khi đăng nhập. Tuỳ chọn: Tắt / Chỉ Quản trị viên / Mọi người dùng. | `Chỉ Quản trị viên` (admins) | Không (fail-open an toàn khi API ngoài mất mạng hoặc timeout). |
| **Cảnh báo admin từ IP mới** | Gửi thông báo Discord khi tài khoản quản trị đăng nhập thành công từ một địa chỉ IP chưa từng xuất hiện trong 10 phiên gần nhất. | **Bật (on)** | Không có. |

---

## 3. Lối thoát khẩn cấp: LACA_SECURITY_BYPASS

Nếu chẳng may bị khoá IP, hoặc cấu hình sai khiến quản trị viên không thể truy cập vào trang admin:

1. Kết nối qua FTP, SSH hoặc trình quản lý tệp tin của hosting (cPanel/Plesk/Local).
2. Mở file `wp-config.php` tại thư mục gốc của WordPress.
3. Thêm dòng khai báo sau trước dòng `/* That's all, stop editing! Happy publishing. */`:
   ```php
   define('LACA_SECURITY_BYPASS', true);
   ```
4. Lưu file.
5. Truy cập lại trang quản trị WordPress bình thường.
   - Khi hằng số này được bật, toàn bộ cơ chế chặn khoá IP (`LoginGuard`), cơ chế khoá username không tồn tại, và ép chuyển hướng 2FA/đổi mật khẩu bị lộ sẽ được vô hiệu hoá tạm thời.
   - Trên đầu màn hình trang quản trị sẽ hiển thị thông báo màu cam cảnh báo hệ thống đang ở chế độ Bypass.
6. Sau khi đăng nhập vào, mở tab **Đăng nhập**, mở khoá IP hoặc thêm IP vào danh sách **IP luôn được phép**.
7. Xoá dòng `define('LACA_SECURITY_BYPASS', true);` khỏi file `wp-config.php` để kích hoạt lại cơ chế bảo vệ.

---

## 4. Cấu hình thông báo bảo mật qua Discord

Để nhận cảnh báo tức thời khi có IP bị khoá, brute-force hoặc admin đăng nhập từ IP lạ:

1. Mở trang quản trị: **Laca Admin** → **Discord Hub**.
2. Tìm kênh **Security** (Kênh bảo mật & cảnh báo đăng nhập).
3. Dán Webhook URL của kênh Discord tương ứng vào ô nhập liệu.
4. Bấm **Lưu cài đặt**.
5. Quay lại **Laca Admin** → **Bảo mật** → Tab **Đăng nhập**, tìm Card **Thông báo đăng nhập**, bấm nút **Gửi thử** để kiểm tra webhook hoạt động.

