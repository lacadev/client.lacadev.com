# Nhật ký thay đổi (Changelog)

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
