# Cấu Trúc Theme Child `lacadev-client-child`

Cập nhật lần cuối: 2026-05-16

## 1. Vai trò hiện tại

Theme child này chỉ còn giữ 3 nhóm trách nhiệm:

- Template cấp trang của dự án
- Asset override cho frontend/admin
- Hook/filter mở rộng riêng của dự án

Shared partials dùng chung đã được chuyển về parent theme `lacadev-client` để parent không còn phụ thuộc ngược vào child.

## 2. Điểm vào chính

- `theme/functions.php:20`
  - Khai báo constant tối thiểu của child
- `theme/functions.php:30`
  - Nạp assets và child theme options
- `theme/functions.php:46`
  - Nạp `app/hooks.php`

## 3. Các file đang dùng thật

- `app/hooks.php:19`
  - Load helper phân trang AJAX
- `app/helpers/ajax-pagination-markup.php:1`
  - Tạo markup phân trang đồng bộ style với parent
- `theme/setup/assets.php:24`
  - Enqueue CSS/JS frontend của child
- `theme/setup/assets.php:75`
  - Enqueue CSS admin của child
- `theme/setup/theme-options.php:10`
  - Chỉ mở rộng Carbon Fields khi có field thật qua filter `lacadev_child_theme_options_fields`
- `theme/archive.php:1`
- `theme/home.php:1`
- `theme/page.php:1`
- `theme/single.php:1`
- `theme/page_templates/template-contact.php:1`

## 4. Các phần đã bỏ

- `app/routes/admin.php`
- `app/routes/ajax.php`
- `app/routes/web.php`
- Toàn bộ `theme/template-parts/*` duplicate

Các file trên đã bị loại bỏ vì child bootstrap hiện không dùng tới, hoặc vì chúng chỉ lặp lại partial chung đã nằm ở parent.

## 5. Khi nào nên thêm code vào child

- Khi dự án cần template cấp trang riêng
- Khi cần CSS/JS override riêng cho dự án
- Khi cần filter/hook chỉ áp dụng cho dự án này

Nếu là logic dùng chung hoặc partial dùng ở nhiều template, ưu tiên đặt ở parent theme.
