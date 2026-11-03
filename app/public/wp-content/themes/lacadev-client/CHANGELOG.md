# Nhật ký thay đổi (Changelog)

## 3.2 (03/11/2026)
- **Bảo mật & Xác thực đa tầng**: Tách riêng 4 API key theo mục đích sử dụng (Tracker, Catalog, Remote, Portal) và hỗ trợ xác thực chữ ký số HMAC-SHA256 chống giả mạo, replay attack.
- **Kênh cập nhật an toàn**: Tải gói theme cập nhật qua endpoint bảo vệ tại trung tâm điều hành `lacadev.com` thay vì link công khai.
- **Chuẩn hoá kết nối**: Tối ưu hoá đường dẫn canonical REST API (`/wp-json/*`), tăng tính ổn định khi đồng bộ block và cập nhật từ xa.
- **Tương thích ngược**: Hỗ trợ chuyển tiếp mượt mà cho các site khách chưa nâng cấp key mới qua cơ chế Legacy Authentication.

## 3.1 (12/10/2026)
- Cập nhật cơ chế đồng bộ Block Gutenberg và ghi nhận hoạt động tự động.
- Nâng cấp giao diện quản trị Laca Admin Settings.
