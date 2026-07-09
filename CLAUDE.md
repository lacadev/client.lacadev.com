# lacadev-client

Site khách chạy theme `lacadev-client` (+ child theme `lacadev-client-child`), là 1 "spoke" báo cáo về hub quản lý dự án `lacadev.com`.

## Trước khi đụng vào tracker / cảnh báo / đồng bộ với hub lacadev

Đọc `app/public/wp-content/themes/lacadev-client/doc/TRACKER_HUB_CLIENT_SYNC.md` trước — file này ghi lại toàn bộ kiến trúc kết nối 2 chiều với hub `lacadev` (tracker log, remote-update, block sync), các thay đổi đã làm theo từng giai đoạn (P0/P1/P2), và hướng dẫn test. Không cần re-explore code từ đầu.

Trạng thái hiện tại: Giai đoạn 1 (P0), Giai đoạn 2 (P1 — chống mất log khi hub gián đoạn), và Giai đoạn 3 (P2 — hợp nhất 2 hệ tracker) đều đã xong. Chỉ còn thao tác cấu hình + test thủ công (xem hướng dẫn test trong file trên).

Các class liên quan chính: `App\Settings\LacaDevTrackerClient` (gửi log/nhận remote-update, giờ là hệ tracker duy nhất — đã gộp thêm theo dõi đổi theme + FIM sâu tùy chọn), `App\Settings\BlockSyncReceiver` (nhận Gutenberg block từ hub). `App\Features\ClientTracker\Tracker` đã bị xoá ở Giai đoạn 3.
