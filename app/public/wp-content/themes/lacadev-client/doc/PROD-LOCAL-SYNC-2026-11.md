# Hướng Dẫn Đồng Bộ Production → Local (Laca Sync)

Tài liệu hướng dẫn vận hành hệ thống đồng bộ một chiều từ máy chủ Production về môi trường Local dành cho lập trình viên và quản trị viên Laca Theme.

---

## 1. Nguyên Tắc An Toàn Cốt Lõi

1. **Chỉ Đọc từ Production**: Tất cả lệnh đồng bộ đều chỉ trích xuất dữ liệu (DB stream qua SSH) hoặc đọc danh sách file (`rsync` chiều prod → local). **Tuyệt đối không có lệnh nào ghi đè dữ liệu từ local lên production**.
2. **Chỉ chạy ở môi trường Local/Development**: Các thao tác thay đổi cơ sở dữ liệu local (`pull-db`, `restore-db`, `local-safe`) chỉ được phép chạy khi `wp_get_environment_type()` là `local` hoặc `development` và URL local khác URL production.
3. **Tự động sao lưu trước khi import**: Mỗi lần chạy `pull-db`, hệ thống luôn tự động xuất bản sao lưu của database local vào `app/sql/backups/local-YYYYmmdd-His.sql.gz`.
4. **Ngắt kết nối gửi ra ngoài (LocalSafe)**: Ngay sau khi kéo DB production về local, hệ thống tự động:
   - Thay thế toàn bộ URL sang domain local.
   - Vô hiệu hóa toàn bộ endpoint và key của Hub/Tracker (không gửi telemetry/alert nhầm về hệ thống quản lý).
   - Tắt chế độ gửi thông báo ngoài production trong Discord Hub.
   - Tắt các plugin chỉ dùng cho production (LiteSpeed Cache, Wordfence, WP Rocket...).
   - Tùy chọn ẩn danh toàn bộ dữ liệu cá nhân (`--anonymize`).

---

## 2. Thiết Lập SSH Key

### 2.1 Máy Dev Cục Bộ
- Đảm bảo máy tính của bạn đã có SSH Key (`~/.ssh/id_rsa.pub` hoặc `~/.ssh/id_ed25519.pub`).
- Thêm Public Key vào cPanel hosting: **cPanel → SSH Access → Manage SSH Keys → Import Key** rồi bấm **Authorize**.
- Thử kết nối trong terminal:
  ```bash
  ssh user@host.example.com -p 22
  ```

### 2.2 Key Riêng Cho GitHub Actions (CI/CD)
Để chạy workflow tự động kéo file từ production:
1. Tạo một cặp SSH key riêng trên máy:
   ```bash
   ssh-keygen -t ed25519 -C "github-actions-sync" -f ~/.ssh/laca_ci_key
   ```
2. Thêm `laca_ci_key.pub` vào cPanel hosting (**Authorize**).
3. Thêm các Secrets vào GitHub repository (**Settings → Secrets and variables → Actions**):
   - `PROD_SSH_HOST`: Địa chỉ IP hoặc hostname hosting.
   - `PROD_SSH_PORT`: Cổng SSH (thường là 22 hoặc cổng cPanel).
   - `PROD_SSH_USER`: Tên tài khoản cPanel/SSH.
   - `PROD_SSH_KEY`: Toàn bộ nội dung private key `~/.ssh/laca_ci_key`.
   - `PROD_SSH_KNOWN_HOSTS`: Kết quả lệnh `ssh-keyscan -p <PORT> <HOST>`.
   - `PROD_PATH` (Variable): Đường dẫn thư mục WordPress (ví dụ `/home/user/public_html`).

---

## 3. Khởi Tạo Dự Án (`wp laca sync-init`)

Tại thư mục dự án local, chạy lệnh WP-CLI:
```bash
wp laca sync-init
```

Lệnh này sẽ tự động sao chép các mẫu chuẩn vào repository:
- `laca-sync.example.yml` ở repo root.
- `.vscode/tasks.json` (các phím tắt tiện lợi trong VS Code).
- `.github/workflows/pull-production.yml` (workflow kéo file từ production).
- `.github/workflows/deploy.yml.proposed` (bản đề xuất chặn deploy đè).
- Tự động bổ sung `laca-sync.yml` và `app/sql/` vào file `.gitignore`.

Sao chép file mẫu và cấu hình:
```bash
cp laca-sync.example.yml laca-sync.yml
```
Mở `laca-sync.yml` và cập nhật thông số SSH của dự án.

---

## 4. Các Lệnh WP-CLI Vận Hành

| Lệnh | Ý nghĩa & Cách dùng |
| --- | --- |
| `wp laca sync-check` | Kiểm tra kết nối SSH, phiên bản WP-CLI trên server, dung lượng DB và chỗ trống ổ đĩa local. |
| `wp laca pull-db` | Kéo toàn bộ database production về local (tự động sao lưu local trước). |
| `wp laca pull-db --anonymize` | Kéo DB và tự động ẩn danh hóa email khách, số điện thoại, đơn liên hệ. |
| `wp laca pull-db --tables=wp_posts,wp_postmeta` | Kéo riêng một số bảng cụ thể (tiết kiệm thời gian với DB lớn). |
| `wp laca drift` | So sánh và phát hiện sai lệch file (`rsync -c`), phiên bản plugin/core giữa prod và local. |
| `wp laca pull-files` | Kéo các tập tin thay đổi từ production về local để chuẩn bị commit. |
| `wp laca pull-files --path=wp-content/plugins/x` | Kéo riêng một thư mục plugin hoặc theme cụ thể. |
| `wp laca pull-media --since=30d` | Tải các thư mục uploads trong 30 ngày gần nhất (phục vụ làm việc offline). |
| `wp laca local-safe` | Chạy riêng quy trình làm sạch và an toàn cho DB local. |
| `wp laca restore-db` | Khôi phục database local từ bản sao lưu gần nhất trong `app/sql/backups/`. |

---

## 5. Sử Dụng Qua Phím Tắt VS Code

Trong VS Code, bạn có thể thực hiện mọi thao tác mà không cần nhớ câu lệnh:
1. Nhấn `Cmd + Shift + P` (Mac) hoặc `Ctrl + Shift + P` (Windows).
2. Chọn **Tasks: Run Task** (hoặc gán phím tắt nhanh).
3. Chọn một trong các tác vụ:
   - **Laca: Kiểm tra kết nối production (sync-check)**
   - **Laca: Kéo DB từ production (pull-db)**
   - **Laca: Kéo DB (Ẩn danh dữ liệu cá nhân)**
   - **Laca: Xem file lệch production (drift)**
   - **Laca: Kéo file lệch từ production (pull-files)**
   - **Laca: Khôi phục DB local gần nhất (restore-db)**

---

## 6. Tính Năng LocalMediaProxy (Không Tải Uploads)

Khi kéo DB về local, bạn **không cần tải hàng chục GB ảnh từ thư mục uploads/**.
- `LocalMediaProxy` tự động phát hiện file ảnh nào chưa có ở ổ đĩa local và chuyển hướng URL hiển thị sang server production (`laca_prod_url`).
- Hiển thị nhãn trên Admin Bar: `Ảnh từ Production (Proxy)` để dễ dàng nhận biết.
- Khi cần làm việc offline (trên máy bay hoặc không có internet), chạy `wp laca pull-media --since=30d` để tải các tháng gần nhất.

---

## 7. Quy Trình Vận Hành Cho 2 Nhóm Khách Hàng

### 7.1 Site Khách Tự Quản Lý (Khách tự cài/cập nhật plugin trong wp-admin)
- Giữ `DISALLOW_FILE_MODS = false` để khách thao tác bình thường.
- Khi khách cập nhật plugin trên hosting, hệ thống **FileWatch Scanner** sẽ tự động phát hiện và gửi thông báo về Discord.
- Lập trình viên chạy `wp laca drift` (hoặc kích hoạt workflow `pull-production.yml` trên GitHub Actions) để kéo code plugin mới về repository.
- Kiểm tra diff, commit lên git và merge vào nhánh `main`.
- Bước kiểm tra drift trong `deploy.yml.proposed` sẽ ngăn chặn tình trạng deploy đè code cũ lên phiên bản mới của khách.

### 7.2 Site Chủ Dự Án Quản Lý Tập Trung
- Khuyến nghị thêm cấu hình sau vào `wp-config.php` trên production:
  ```php
  define('DISALLOW_FILE_MODS', true);
  define('DISALLOW_FILE_EDIT', true);
  ```
- Toàn bộ việc cài mới, cập nhật plugin/theme đều được thực hiện tại môi trường Local → kiểm thử → commit → deploy tự động qua GitHub Actions.
- Trang **Kiểm tra bảo mật (Security Audit)** của theme sẽ hiển thị trạng thái `DISALLOW_FILE_MODS = true` ở mức an toàn tuyệt đối.

---

## 8. Xử Lý Sự Cố Thường Gặp

### 8.1 Hosting Chưa Có WP-CLI
Nếu lệnh `sync-check` báo lỗi `Không thể chạy WP-CLI bằng lệnh "wp"`:
1. Đăng nhập SSH vào hosting:
   ```bash
   ssh user@host.example.com
   ```
2. Cài đặt WP-CLI cục bộ trong thư mục người dùng:
   ```bash
   mkdir -p ~/bin
   curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
   chmod +x wp-cli.phar
   mv wp-cli.phar ~/bin/wp
   ```
3. Cập nhật cấu hình trong `laca-sync.yml`:
   ```yaml
   production:
     wp: "~/bin/wp"
   ```

### 8.2 Database Dung Lượng Quá Lớn (> 1 GB)
- Hệ thống sử dụng cơ chế streaming trực tiếp qua `ssh ... | gzip -c`, không tốn RAM máy chủ và máy local.
- Đảm bảo trong `laca-sync.yml` đã cấu hình loại trừ các bảng log và bảng tạm:
  ```yaml
  db:
    exclude_tables:
      - "wflogs"
      - "actionscheduler_logs"
      - "laca_project_logs"
    exclude_data_only:
      - "laca_email_log"
      - "laca_login_log"
      - "laca_contact_submissions"
  ```
- Nếu chỉ cần kéo nội dung bài viết và media để sửa giao diện:
  ```bash
  wp laca pull-db --tables=wp_posts,wp_postmeta,wp_terms,wp_term_taxonomy,wp_term_relationships
  ```

### 8.3 Khôi Phục Lại Dữ Liệu Local Nếu Kéo Nhầm
Chỉ cần chạy lệnh:
```bash
wp laca restore-db
```
Hệ thống sẽ lập tức khôi phục lại bản sao lưu local được tạo ngay trước khi kéo.

