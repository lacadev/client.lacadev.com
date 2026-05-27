# Tình Trạng Plugin: Laca Self-Ordering KDS

Tài liệu này mô tả trạng thái hiện tại của plugin để có thể gửi cho ChatGPT, Gemini hoặc một developer khác tư vấn thêm chức năng, tối ưu UI/UX, tối ưu vận hành hoặc rà soát kỹ thuật.

## 1. Thông Tin Tổng Quan

- Tên plugin: Laca Self-Ordering KDS
- Phiên bản hiện tại: 1.0.33
- Nền tảng: WordPress plugin custom, không dùng WooCommerce
- Mục tiêu: phục vụ gian hàng ẩm thực tại sự kiện/festival, giúp khách tự gọi món bằng điện thoại, thanh toán VietQR, người bán xử lý đơn trên KDS, giảm thời gian chờ.
- Định hướng UI: retro, clean, minimal, mobile-first, font Quicksand.
- Đối tượng dùng: có thể tùy biến cho nhiều gian hàng khác nhau, không chỉ gian hàng La Cà.

## 2. Kiến Trúc Hiện Có

Plugin được xây từ đầu bằng các thành phần chính:

- Custom Post Type `laca_food`: quản lý món ăn/món uống.
- Taxonomy `laca_food_category`: phân loại món, ví dụ đồ ăn vặt, nước, món chính.
- Custom Post Type `laca_combo`: quản lý combo/set món từ các món lẻ.
- Custom database tables:
  - `wp_laca_orders`: lưu đơn hàng.
  - `wp_laca_payment_logs`: lưu log webhook/thanh toán.
  - `wp_laca_notification_logs`: lưu log SMS/Zalo/Web Push.
  - `wp_laca_push_subscriptions`: lưu đăng ký Web Push theo đơn.
- WordPress REST API cho frontend, trạng thái đơn, webhook thanh toán, pickup screen và PWA.
- WordPress AJAX cho KDS admin, cập nhật trạng thái đơn và sửa nhanh món.

## 3. Cấu Trúc Menu Admin

Plugin gom các màn hình vận hành vào menu `KDS Manager`.

Các mục hiện có:

- Món ăn: danh sách món, sửa nhanh ảnh/tên/giá/trạng thái.
- Danh mục món: quản lý phân loại món.
- Combo / Set món: tạo combo từ món lẻ.
- Khuyến mãi: tạo rule giảm giá/tặng kèm.
- Quản lý đơn: màn hình KDS xử lý đơn.
- Doanh thu: báo cáo doanh thu chi tiết.
- Tin nhắn: log gửi SMS/Zalo/Web Push.
- Đối soát: log webhook/thanh toán.
- Cài đặt: thương hiệu, VietQR, vận hành, SePay, SMS, Web Push, trang menu.

## 4. Quản Lý Món Ăn

Đã có:

- Thêm/sửa món bằng CPT `laca_food`.
- Mỗi món có tiêu đề, ảnh đại diện, giá, trạng thái còn bán/hết món.
- Phân loại món bằng `laca_food_category`.
- Sửa nhanh ngay ở danh sách món:
  - Click vào ảnh để đổi ảnh.
  - Nút X đỏ để xóa ảnh.
  - Sửa tên món, giá, trạng thái còn bán.
  - Tự lưu bằng AJAX khi rời khỏi input.
- Variant cho món:
  - Có thể tạo nhóm tùy chọn như size, gia vị, nước sốt.
  - Mỗi option có thể có giá cộng thêm.
  - Variant được lưu vào đơn và hiển thị trong KDS/doanh thu.

## 5. Combo / Set Món

Đã có:

- CPT `laca_combo`.
- Admin có màn hình cấu hình combo:
  - Chọn các món lẻ trong combo.
  - Mỗi món chỉ được chọn một lần trong cùng combo.
  - Nếu cần 2 phần của cùng một món, tăng số lượng ở dòng đó.
  - Nhập giá bán combo.
  - Nhập nhãn hiển thị, ví dụ `Combo tiết kiệm`.
  - Bật/tắt trạng thái đang bán.
- Frontend hiển thị combo như một sản phẩm riêng.
- Menu hiển thị giá gốc cộng từ món lẻ và giá combo đã giảm.

## 6. Khuyến Mãi / Tặng Kèm

Đã có hệ thống promotion rules trong admin.

Các kiểu rule hiện hỗ trợ:

- Giảm giá theo số lượng món.
- Giảm giá theo tổng tiền đơn.
- Tặng kèm khi đủ điều kiện.
- Cho phép cấu hình danh sách món được chọn làm quà tặng.

Luồng tặng kèm:

- Admin tạo rule và chọn các món có thể tặng.
- Khi giỏ hàng đủ điều kiện, khách thấy lựa chọn món tặng kèm.
- Món tặng kèm được thêm vào đơn với giá 0đ.
- KDS và doanh thu đều hiển thị món tặng kèm để nhân viên biết chuẩn bị.

## 7. Frontend Gọi Món

Đã có:

- Shortcode `[laca_menu_app]`.
- Plugin tự tạo page menu công khai bằng page template riêng.
- Giao diện menu mobile-first, dùng Quicksand, phong cách retro-clean.
- Hiển thị món dạng grid/card.
- Bộ lọc theo danh mục món.
- Tìm món bằng AJAX qua REST API.
- Giỏ hàng nổi/khung giỏ hàng để xem nhanh.
- Có tăng/giảm số lượng món.
- Có chọn variant nếu món có tùy chọn.
- Nút thanh toán nổi bật.
- Checkout chỉ yêu cầu số điện thoại.
- Sau khi đặt đơn, tạo order trạng thái `pending` và hiển thị VietQR.

## 8. VietQR Và Thanh Toán

Đã có:

- Admin nhập thông tin ngân hàng trong UI:
  - Bank BIN.
  - Số tài khoản.
  - Tên chủ tài khoản.
  - Prefix nội dung chuyển khoản.
- QR tạo tự động bằng URL VietQR.
- Nội dung chuyển khoản có dạng `PREFIX0001`, ví dụ `ORDER0001` hoặc `9XQUA0001`.
- Prefix có thể thay đổi trong admin, không bị cố định là LACA.
- Có thời gian giữ QR, cấu hình được trong admin.
- Hết thời gian mà đơn vẫn `pending` thì đơn tự hủy.
- Nếu webhook đến muộn nhưng giao dịch hợp lệ, plugin vẫn có logic ghi nhận lại thanh toán.
- Có nút hỗ trợ thanh toán trên mobile:
  - Mở QR lớn.
  - Sao chép nội dung chuyển khoản.

Ghi chú quan trọng:

- Plugin không thể mở trực tiếp mọi app ngân hàng vì không có deep link chung cho tất cả ngân hàng.
- Luồng ổn định nhất hiện tại là hiển thị QR và cho khách copy nội dung chuyển khoản.

## 9. SePay / Webhook Thanh Toán

Đã có REST endpoint:

```text
POST /wp-json/laca/v1/payment-webhook
```

Webhook được bảo vệ bằng Webhook Secret cấu hình trong admin.

Plugin hỗ trợ nhận nhiều kiểu header xác thực:

- `Authorization: Apikey WEBHOOK_SECRET`
- `Authorization: Bearer WEBHOOK_SECRET`
- `Authorization: WEBHOOK_SECRET`
- `X-Laca-Webhook-Secret: WEBHOOK_SECRET`

Khi webhook hợp lệ:

- Plugin đọc số tiền, nội dung chuyển khoản/payment code.
- Match với mã đơn theo prefix + ID đơn.
- Nếu số tiền khớp, chuyển đơn từ `pending` sang `paid`.
- Ghi log vào `KDS Manager -> Đối soát`.

## 10. KDS / Quản Lý Đơn

Đã có:

- Trang `KDS Manager -> Quản lý đơn`.
- Giao diện dạng card/grid tối ưu cho iPad/mobile.
- Tự refresh bằng AJAX theo cấu hình, mặc định khoảng 2 giây.
- Trang theo dõi đơn của khách có polling riêng, mặc định 7 giây để giảm tải server.
- Hiển thị đơn `pending` và `paid`.
- Mỗi card hiển thị:
  - Mã đơn.
  - Số điện thoại đầy đủ.
  - Thời gian tạo đơn.
  - Danh sách món, số lượng, variant, combo, món tặng.
  - Tổng tiền.
  - Trạng thái.
- Actions:
  - `Xác nhận TT`: chuyển đơn sang `paid` thủ công.
  - `Hoàn thành`: hoàn tất đơn.
  - `Hủy`: hủy đơn.
  - `Hoàn tác`: ở khu đơn vừa xử lý.

Quy tắc doanh thu/thông báo:

- Nếu đơn đang `pending` mà admin bấm `Hoàn thành`, plugin xem như hoàn thành ngoài hệ thống, không gửi SMS và không tính vào doanh thu online.
- Nếu đơn đang `paid` mà admin bấm `Hoàn thành`, plugin gửi SMS/Web Push và đơn được tính vào doanh thu.
- Nếu bấm nhầm, có thể hoàn tác trạng thái gần nhất, nhưng SMS đã gửi thì không thu hồi được.

## 11. Theo Dõi Đơn Của Khách

Đã có:

- Shortcode `[laca_order_status]`.
- Plugin tự tạo page theo dõi trạng thái đơn.
- Khách sau khi đặt đơn có thể theo dõi realtime trạng thái:
  - Chờ thanh toán.
  - Đã thanh toán.
  - Hoàn thành.
  - Đã hủy.
- Trang status polling khoảng 2 giây.
- Có thể bật Browser Notification nếu trình duyệt cho phép.

## 12. Pickup Screen

Đã có:

- Shortcode `[laca_pickup_screen]`.
- Plugin tự tạo page pickup screen.
- Hiển thị các đơn đã hoàn thành gần đây để khách nhìn lên màn hình/quầy nhận món.
- Dữ liệu lấy qua REST API `/wp-json/laca/v1/pickup-orders`.

## 13. SMS / Zalo / Notification

Đã có:

- Hàm `laca_send_notification( $order_id )` gửi khi admin bấm `Hoàn thành` từ trạng thái `paid`.
- Admin UI cấu hình provider.
- Hỗ trợ SpeedSMS.
- Hỗ trợ eSMS.
- Có form gửi SMS test trong admin.
- Có nội dung tin nhắn mẫu chỉnh được trong admin.
- Có log gửi tin nhắn ở `KDS Manager -> Tin nhắn`.

Nội dung tin nhắn hỗ trợ biến:

- `{stall_name}`
- `{order_id}`
- `{queue_number}`
- `{phone}`
- `{total}`

Ví dụ nội dung mặc định:

```text
{stall_name} thông báo: Đơn hàng #{order_id} của bạn đã hoàn thành. Vui lòng đến quầy nhận món nhé!
```

## 14. Browser Notification / Web Push / PWA

Đã có:

- Browser Notification khi khách đang mở trang theo dõi đơn.
- Web Push/PWA song song với SMS nếu cấu hình VAPID key.
- Service worker `assets/js/laca-push-sw.js`.
- REST endpoint lưu push subscription.
- Bảng riêng lưu push subscription theo order/token.

Điều kiện:

- Website cần HTTPS thật.
- Web Push cần VAPID public/private key.
- iPhone có thể cần mở bằng Safari và Add to Home Screen để ổn định hơn.

## 15. Doanh Thu / Báo Cáo

Đã có trang `KDS Manager -> Doanh thu`.

Hiển thị:

- Doanh thu hôm nay.
- Doanh thu 7 ngày gần nhất.
- Doanh thu theo khoảng lọc ngày.
- Số đơn.
- Giá trị đơn trung bình.
- Tổng số món đã bán.
- Bảng doanh thu theo ngày.
- Bảng món bán chạy.
- Chi tiết đơn hàng trong khoảng lọc.

Quy tắc tính doanh thu:

- Chỉ tính đơn có `paid_at`.
- Tính các đơn trạng thái `paid` hoặc `completed`.
- Đơn hoàn thành từ trạng thái `pending` không được tính doanh thu online.

## 16. Log / Đối Soát

Đã có:

- Log thanh toán/webhook ở `KDS Manager -> Đối soát`.
- Log SMS/Zalo/Web Push ở `KDS Manager -> Tin nhắn`.
- Log lưu request/response để debug lỗi provider.

Log thanh toán giúp kiểm tra:

- Webhook có đến không.
- Có match đơn không.
- Có sai số tiền không.
- Có sai nội dung chuyển khoản không.
- Có giao dịch tiền ra không.

Log tin nhắn giúp kiểm tra:

- Provider đã nhận request chưa.
- HTTP code.
- Provider code.
- Request JSON.
- Response body/error message.

## 17. REST API Hiện Có

Namespace:

```text
/wp-json/laca/v1
```

Endpoints:

- `GET /menu-items`: lấy danh sách món/combo, hỗ trợ search và category.
- `GET /pwa-manifest`: manifest nhẹ cho PWA.
- `POST /orders`: tạo đơn hàng.
- `GET /orders/status`: lấy trạng thái đơn bằng order ID + public token.
- `POST /push-subscription`: lưu đăng ký Web Push.
- `GET /pickup-orders`: lấy đơn hoàn thành cho pickup screen.
- `POST /payment-webhook`: nhận webhook thanh toán.

## 18. AJAX Admin Hiện Có

Các action chính:

- `laca_kds_get_orders`: lấy danh sách đơn mở và đơn vừa xử lý.
- `laca_kds_update_status`: đổi trạng thái đơn, hoàn tác, gửi notification khi phù hợp.
- `laca_kds_get_food_items`: lấy danh sách món cho quản lý nhanh.
- `laca_kds_toggle_food_availability`: bật/tắt còn món.
- `laca_kds_inline_update_food`: sửa nhanh món ở danh sách món.
- `laca_kds_test_notification`: gửi SMS test từ trang cài đặt.

## 19. Cài Đặt Có Thể Tùy Biến

Trong admin UI hiện có:

- Tên gian hàng.
- Headline menu.
- Mô tả menu.
- Màu chủ đề.
- Thông tin VietQR.
- Prefix chuyển khoản.
- Thời gian giữ QR.
- Tốc độ refresh KDS.
- Tốc độ refresh trang theo dõi đơn của khách.
- TTL cache REST menu.
- Giới hạn số đơn đang chờ.
- Thông báo khi quầy quá tải.
- Webhook Secret.
- Provider SMS.
- API endpoint/token/secret/sender/sms type.
- Tin nhắn mẫu.
- Web Push VAPID keys.
- Promotion rules.
- Đồng bộ/tạo lại các page công khai.

## 20. UI/UX Hiện Tại

Đã làm:

- Admin UI thống nhất hơn trong nhóm KDS Manager.
- Menu món đã nằm trong KDS Manager.
- Font Quicksand.
- Giao diện retro-clean, giấy ấm, màu nhấn cam, ít bold hơn.
- Mobile-first cho frontend.
- KDS tối ưu cho iPad/mobile.
- Food list hỗ trợ sửa nhanh bằng AJAX.

## 21. Điểm Cần Tư Vấn / Có Thể Tối Ưu Tiếp

Các hướng nên hỏi thêm AI/developer:

- Có nên thêm quản lý tồn kho/nguyên liệu theo món không?
- Có nên thêm giới hạn số lượng bán của từng món trong từng ca không?
- Có nên thêm kitchen station, ví dụ bếp chiên, bếp nước, quầy đóng gói không?
- Có nên thêm màn hình gọi số thứ tự lớn tại quầy không?
- Có nên thêm in tem/in bill qua máy in nhiệt không?
- Có nên thêm chế độ offline/local fallback nếu mạng yếu tại sự kiện không?
- Có nên thêm export CSV doanh thu, món bán chạy, log thanh toán không?
- Có nên thêm phân quyền nhân viên, ví dụ thu ngân/bếp/quản lý không?
- Có nên thêm mã giảm giá thủ công hoặc voucher QR không?
- Có nên thêm QR riêng cho từng bàn/khu vực không?
- Có nên thêm đa gian hàng/multi-stall trong cùng một site không?
- Có nên thêm cảnh báo khi SePay webhook không hoạt động trong X phút không?
- Có nên thêm dashboard realtime bằng WebSocket/SSE thay vì AJAX polling không?
- Có nên thêm queue number riêng khác order ID cho dễ gọi khách không?
- Có nên thêm trạng thái `preparing`, `ready`, `picked_up` để quy trình rõ hơn không?
- Có nên thêm chống spam order bằng rate limit/captcha nhẹ không?
- Có nên thêm kiểm thử tự động cho REST API/webhook/order flow không?

## 22. Rủi Ro / Giới Hạn Hiện Tại

- KDS đang dùng AJAX polling, chưa phải realtime push thật bằng WebSocket/SSE.
- Web Push phụ thuộc HTTPS, quyền trình duyệt và hỗ trợ thiết bị.
- SMS phụ thuộc provider, tài khoản, loại SMS, brandname/sender được duyệt.
- VietQR chỉ tạo QR/copy nội dung, không có deep link chung mở mọi app ngân hàng.
- Doanh thu web chỉ chính xác với đơn được xác nhận thanh toán trong hệ thống.
- Chưa thấy cơ chế tồn kho/nguyên liệu.
- Chưa thấy phân quyền nhân viên chi tiết.
- Chưa thấy export báo cáo.
- Chưa thấy in bill/in tem.
- Chưa có automated test suite rõ ràng.

## 23. Prompt Gợi Ý Để Hỏi ChatGPT/Gemini

Có thể copy prompt này:

```text
Tôi đang xây một WordPress plugin tên Laca Self-Ordering KDS cho gian hàng ẩm thực festival. Plugin không dùng WooCommerce, dùng CPT, custom DB tables, REST API và AJAX.

Plugin hiện đã có: quản lý món ăn, danh mục món, variant, combo/set món, khuyến mãi/tặng kèm, frontend menu mobile-first, giỏ hàng, VietQR, SePay webhook, KDS quản lý đơn, SMS/eSMS/SpeedSMS, Browser Notification/Web Push/PWA, pickup screen, doanh thu chi tiết, log thanh toán và log tin nhắn.

Mục tiêu là tối ưu quy trình order/thanh toán/nhận món cho gian hàng đông khách, dễ dùng cho nhiều gian hàng khác nhau, giao diện retro-clean-minimal, ưu tiên mobile và iPad.

Hãy tư vấn cho tôi:
1. Plugin này còn thiếu chức năng gì quan trọng cho vận hành thực tế ở sự kiện đông người?
2. Nên ưu tiên roadmap theo thứ tự nào để tăng tốc độ phục vụ và giảm lỗi vận hành?
3. Có rủi ro kỹ thuật, bảo mật, thanh toán, SMS/Web Push nào cần xử lý trước khi dùng thật?
4. Có cải tiến UI/UX nào giúp khách đặt hàng nhanh hơn trên điện thoại?
5. Có kiến trúc nào nên thay đổi để plugin có thể dùng cho nhiều gian hàng khác nhau?
```

## 24. File Chính Trong Plugin

- `laca-self-ordering-kds.php`: bootstrap plugin, helpers, QR, notification, Web Push.
- `includes/class-laca-food-cpt.php`: CPT món ăn, taxonomy, variant, sửa nhanh món.
- `includes/class-laca-combo-cpt.php`: CPT combo/set món.
- `includes/class-laca-orders-repository.php`: custom tables, đơn hàng, doanh thu, logs.
- `includes/class-laca-admin-kds.php`: admin menu, KDS, doanh thu, logs, AJAX.
- `includes/class-laca-settings.php`: cài đặt, page template, promotions, SMS test.
- `includes/class-laca-frontend.php`: shortcodes, frontend assets, menu/status/pickup UI.
- `includes/class-laca-rest-api.php`: REST API, tạo đơn, menu items, webhook.
- `assets/js/frontend.js`: frontend menu/cart/checkout.
- `assets/js/admin-kds.js`: KDS AJAX.
- `assets/js/settings.js`: admin settings/promotion/SMS test.
- `assets/css/frontend.css`: UI frontend.
- `assets/css/admin-kds.css`: UI admin plugin.
