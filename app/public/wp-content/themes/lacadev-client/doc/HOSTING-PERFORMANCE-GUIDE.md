# Hướng Dẫn Cấu Hình Hosting Tối Ưu Hiệu Năng & Cache Cho Laca Theme

Tài liệu này hướng dẫn quản trị viên cấu hình máy chủ hosting để phát huy tối đa tốc độ của Laca Theme: **≤ 25 query**, **0 lần ghi DB** ở request xem trang, và sẵn sàng cho **bộ nhớ đệm toàn trang (Full-Page Cache)** lưu 24–48 giờ mà không bị lỗi tương tác.

---

## 1. Yêu Cầu Máy Chủ Khuyến Nghị

- **Web Server:** Nginx hoặc LiteSpeed Enterprise / OpenLiteSpeed.
- **PHP:** PHP 8.1 trở lên (khuyên dùng PHP 8.2 hoặc 8.3).
- **Database:** MariaDB 10.6+ hoặc MySQL 8.0+.
- **Bộ nhớ PHP (`memory_limit`):** Tối thiểu 256MB (khuyên dùng 512MB).
- **Zend OPcache:** BẮT BUỘC BẬT.

---

## 2. Cấu Hình Zend OPcache

Trong file `php.ini` (hoặc `/etc/php/8.x/fpm/conf.d/10-opcache.ini`):

```ini
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.revalidate_freq=60
opcache.validate_timestamps=1
opcache.save_comments=1
opcache.fast_shutdown=1
```

> **Lưu ý:** `opcache.save_comments=1` là bắt buộc để các thư viện sử dụng docblock annotations (như Carbon Fields / Doctrine) hoạt động ổn định.

---

## 3. Cấu Hình Redis Object Cache

Cài đặt Redis server và plugin **Redis Object Cache** (hoặc **WP Redis**):

```bash
# Ubuntu/Debian
sudo apt install redis-server php-redis -y
sudo systemctl enable redis-server --now
```

Thêm vào `wp-config.php`:

```php
define('WP_CACHE', true);
define('WP_REDIS_HOST', '127.0.0.1');
define('WP_REDIS_PORT', 6379);
define('WP_REDIS_TIMEOUT', 1);
define('WP_REDIS_READ_TIMEOUT', 1);
```

Lợi ích: Giảm số query truy vấn options, transients và post meta lặp lại từ database về 0.

---

## 4. Cấu Hình Full-Page Cache (Bộ Nhớ Đệm Toàn Trang)

Laca Theme đã được thiết kế sẵn sàng cho Full-Page Cache:
- Request xem trang **hoàn toàn không ghi DB** (0 WRITES).
- Lượt xem bài viết được đếm bất đồng bộ qua **Beacon API + REST Endpoint `/wp-json/laca/v1/view`**.
- Thao tác chỉ đọc (Tìm kiếm AJAX, Load More) **không bị chặn bởi Nonce hết hạn**.
- Thao tác ghi (Form liên hệ, Newsletter) tự động lấy Nonce tươi qua **`GET /wp-json/laca/v1/nonce`** khi người dùng tương tác.

### Phương án A: Nginx FastCGI Cache (Khuyên Dùng Cho Nginx VPS)

Cấu hình trong Nginx vhost:

```nginx
# Định nghĩa đường dẫn lưu cache
fastcgi_cache_path /var/run/nginx-cache levels=1:2 keys_zone=WORDPRESS:100m max_size=2g inactive=48h use_temp_path=off;
fastcgi_cache_key "$scheme$request_method$host$request_uri";

# Bỏ qua cache cho các request riêng tư
set $skip_cache 0;
if ($request_method = POST) { set $skip_cache 1; }
if ($query_string != "") { set $skip_cache 1; }
if ($request_uri ~* "/wp-admin/|/xmlrpc.php|wp-.*.php|/feed/|index.php|sitemap(_index)?.xml") { set $skip_cache 1; }
if ($http_cookie ~* "comment_author|wordpress_[a-f0-9]+|wp-postpass|wordpress_no_cache|wordpress_logged_in") { set $skip_cache 1; }

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;

    fastcgi_cache WORDPRESS;
    fastcgi_cache_bypass $skip_cache;
    fastcgi_no_cache $skip_cache;
    fastcgi_cache_valid 200 301 302 48h;
    fastcgi_cache_use_stale error timeout updating invalid_header http_500 http_503;
    add_header X-FastCGI-Cache $upstream_cache_status;
}
```

Kết hợp với plugin **Nginx Helper** để tự động purge cache khi cập nhật nội dung.

### Phương án B: LiteSpeed Cache (Khuyên Dùng Cho Host LiteSpeed)

- Cài đặt plugin **LiteSpeed Cache**.
- Bật Cache, thiết lập TTL Public: `86400` giây (24 giờ) hoặc `172800` giây (48 giờ).
- Laca Theme đã tích hợp sẵn action `litespeed_purge_all` và `litespeed_purge_post` tự động.

### Phương án C: Cloudflare Edge Cache / Cache Rules

- Tạo Cache Rule trên Cloudflare Dashboard:
  - **URI Path** does not contain `/wp-admin/` AND does not contain `/wp-login.php` AND does not contain `/wp-json/`
  - **Cookie** does not contain `wordpress_logged_in_`
  - **Cache eligibility:** Eligible for cache
  - **Edge Cache TTL:** 24 hours

---

## 5. Tối Ưu WP-Cron Hệ Thống

Để tránh WP-Cron chạy làm chậm các request của khách, nên chuyển sang System Cron thật của Linux.

Thêm vào `wp-config.php`:

```php
define('DISABLE_WP_CRON', true);
```

Thêm vào `crontab` của user chạy web (vd `www-data`):

```bash
*/5 * * * * php /var/www/html/wp-cron.php > /dev/null 2>&1
```

Điều này giúp các tác vụ định kỳ của Laca Theme (như gom lượt xem bài viết mỗi 5 phút `laca_flush_view_counts` hoặc dọn dẹp log) chạy chính xác đúng giờ mà không phụ thuộc vào lượt truy cập của người dùng.

