# Cấu Trúc Parent/Child Theme `lacadev-client`

Cập nhật lần cuối: 2026-05-16

Tài liệu này mô tả ranh giới hiện tại giữa:

- `app/public/wp-content/themes/lacadev-client`
- `app/public/wp-content/themes/lacadev-client-child`

Mục tiêu của đợt cleanup này là:

- Parent theme giữ toàn bộ partial dùng chung và phần core.
- Child theme chỉ giữ template cấp trang, assets override và hook mở rộng thực sự dùng.
- Loại bỏ scaffold placeholder không chạy.

## 1. Parent Theme `lacadev-client`

### 1.1 Bootstrap chính

- File:
  - `theme/functions.php:111`
  - `theme/functions.php:136`
- Mục đích:
  - Load Composer autoload.
  - Bootstrap WPEmerge.
  - Load `app/helpers.php`, `app/hooks.php`.
  - Nạp các module `theme/setup/*` trong `after_setup_theme`.

### 1.2 Helper điều hướng tới shared partials

- File:
  - `app/helpers/template_tags.php:276`
  - `app/helpers/template_tags.php:281`
- Mục đích:
  - `theBreadcrumb()` luôn gọi `theme/template-parts/breadcrumb.php`
  - `theShareSocials()` luôn gọi `theme/template-parts/share_box.php`

### 1.3 Shared template-parts dùng chung cho cả parent và child

- File:
  - `theme/template-parts/breadcrumb.php:1`
  - `theme/template-parts/page-hero.php:1`
  - `theme/template-parts/share_box.php:1`
  - `theme/template-parts/post-hero.php:1`
  - `theme/template-parts/rating-box.php:1`
  - `theme/template-parts/loop-post.php:1`
  - `theme/template-parts/loop-service.php:1`
  - `theme/template-parts/loop-product.php:1`
- Mục đích:
  - Parent sở hữu toàn bộ partial dùng chung để có thể chạy standalone.
  - Child theme khi gọi `get_template_part('template-parts/...')` sẽ fallback về parent nếu không override.

### 1.4 Các template parent đang dùng trực tiếp shared partials

- File:
  - `theme/page.php:13`
  - `theme/index.php:10`
  - `theme/author.php:15`
  - `theme/search.php:17`
  - `theme/page_templates/template-contact.php:20`
- Mục đích:
  - Các template này phụ thuộc vào breadcrumb, page hero và loop cards dùng chung.
  - Sau cleanup, chúng không còn phụ thuộc vào file đang nằm ở child theme nữa.

## 2. Child Theme `lacadev-client-child`

### 2.1 Bootstrap child theme

- File:
  - `theme/functions.php:20`
  - `theme/functions.php:30`
  - `theme/functions.php:46`
- Mục đích:
  - Khai báo các constant tối thiểu cho child.
  - Nạp assets child.
  - Nạp child theme options nếu dự án có field thật.
  - Nạp `app/hooks.php` để đăng ký override/filter riêng.

### 2.2 Child hooks hiện còn dùng

- File:
  - `app/hooks.php:19`
  - `app/helpers/ajax-pagination-markup.php:1`
- Mục đích:
  - Child chỉ còn một helper PHP thực tế đang được load là markup phân trang AJAX.
  - Đây là helper đồng bộ class/CSS với phân trang của parent.

### 2.3 Child asset overrides

- File:
  - `theme/setup/assets.php:24`
  - `theme/setup/assets.php:75`
- Mục đích:
  - Enqueue CSS/JS override của child ở frontend và admin.
  - Mọi handle phụ thuộc vào constants của parent để tránh magic string.

### 2.4 Child theme options

- File:
  - `theme/setup/theme-options.php:10`
- Mục đích:
  - Child không còn tự tạo tab Carbon Fields rỗng.
  - Tab chỉ được đăng ký khi có code khác bơm field qua filter `lacadev_child_theme_options_fields`.

### 2.5 Template cấp trang child hiện còn giữ

- File:
  - `theme/archive.php:1`
  - `theme/home.php:1`
  - `theme/page.php:1`
  - `theme/single.php:1`
  - `theme/page_templates/template-contact.php:1`
- Mục đích:
  - Đây là nơi child giữ markup cấp trang của dự án.
  - Các template này giờ chỉ gọi shared partial từ parent, không cần tự giữ bản copy nữa.

## 3. Các phần đã dọn trong đợt này

### 3.1 Đã chuyển ownership partial dùng chung về parent

- File đã xóa khỏi child:
  - `theme/template-parts/breadcrumb.php`
  - `theme/template-parts/page-hero.php`
  - `theme/template-parts/share_box.php`
  - `theme/template-parts/post-hero.php`
  - `theme/template-parts/rating-box.php`
  - `theme/template-parts/loop-post.php`
  - `theme/template-parts/loop-service.php`
  - `theme/template-parts/comment-single.php`
- Kết quả:
  - Child gọn hơn.
  - Parent tự chủ hơn.
  - Không còn tình trạng parent gọi partial nhưng file thật lại nằm ở sibling theme.

### 3.2 Đã xóa scaffold route placeholder ở child

- File đã xóa:
  - `app/routes/admin.php`
  - `app/routes/ajax.php`
  - `app/routes/web.php`
- Lý do:
  - Child bootstrap hiện không load hệ route này.
  - Giữ các file placeholder chỉ làm cấu trúc nhìn nặng và gây hiểu nhầm.

## 4. Test đang bảo vệ ranh giới mới

- File:
  - `tests/Unit/ParentChildThemeBoundaryTest.php:5`
- Mục đích:
  - Kiểm tra parent có đủ shared template-parts bắt buộc.
  - Kiểm tra child không tái xuất hiện route placeholder hoặc duplicate partial.
  - Kiểm tra child theme options ở trạng thái “dormant” nếu chưa có field thật.

## 5. Checklist khi sửa tiếp sau này

- Nếu thêm partial dùng chung:
  - ưu tiên đặt ở `lacadev-client/theme/template-parts/`
- Nếu chỉ override giao diện theo dự án:
  - đặt ở template cấp trang của child trước
- Nếu child cần Carbon Fields riêng:
  - thêm field qua filter `lacadev_child_theme_options_fields`
- Không tạo lại `app/routes/*` trong child trừ khi child thực sự có bootstrap riêng cho routes đó
