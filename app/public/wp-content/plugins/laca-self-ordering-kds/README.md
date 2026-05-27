# Laca Self-Ordering KDS Setup Guide

Plugin nay dung cho gian hang am thuc tu order, thanh toan VietQR, KDS bep, pickup screen va SMS thong bao hoan thanh don.

## 1. Cau hinh co ban

Vao WordPress Admin -> KDS Manager -> Cai dat La Ca.

Dien:

- Ten gian hang.
- Headline menu.
- Mo ta ngan.
- Mau chu de.
- Thong tin ngan hang VietQR.
- Prefix chuyen khoan, vi du `9XQUA` hoac `ORDER`.
- Thoi gian giu QR, khuyen nghi `600` den `900` giay khi test that.
- Toc do cap nhat KDS, khuyen nghi `2000` ms.

Noi dung chuyen khoan cua moi don se co dang:

```text
PREFIX0001
```

Vi du:

```text
9XQUA0001
```

## 2. Quan ly mon, combo va variant

Mon le:

- Vao `Foods` de them mon, anh, gia va phan loai.
- O man hinh danh sach mon, co the sua nhanh anh, ten, gia va trang thai con ban. Click ra khoi o nhap la tu luu.
- Trong trang chi tiet mon, muc `Tuy chon mon / Variant` dung de them Size, Gia vi, Nuoc sot...

Vi du variant:

- Nhom `Size`: `Nho +0d`, `Lon +5000d`.
- Nhom `Gia vi`: `Khong cay +0d`, `It cay +0d`, `Cay nhieu +0d`.
- Nhom `Nuoc sot`: `Sot me +0d`, `Sot cay +0d`.

Combo / set mon:

- Vao `KDS Manager -> Combo / Set mon`.
- Chon cac mon le de ghep thanh combo va nhap gia ban combo.
- Moi mon chi duoc chon 1 lan trong 1 combo. Neu combo can 2 phan cung mon, tang cot `SL` cua dong do.
- Menu se hien gia goc cong tu cac mon le va gia combo da giam.

Tang kem theo dieu kien:

- Vao `KDS Manager -> Cai dat La Ca -> Combo / khuyen mai`.
- Tao luat `Tang kem`, chon dieu kien theo so luong mon hoac tong tien.
- O muc `Mon khach duoc chon lam qua`, chon cac mon co the tang kem, vi du `Nuoc mot`, `Tra da`.
- Khi gio hang du dieu kien, khach se thay dropdown trong gio hang de chon 1 mon tang kem.
- Don tao ra se luu mon tang kem voi gia `0d` va hien trong KDS.

## 3. Cau hinh SePay webhook

Webhook URL trong plugin:

```text
https://your-domain.com/wp-json/laca/v1/payment-webhook
```

Trong SePay:

1. Dang nhap `https://my.sepay.vn`.
2. Ket noi tai khoan ngan hang nhan tien.
3. Vao Webhooks.
4. Tao webhook moi.
5. Dien Webhook URL o tren.
6. Chon xac thuc API Key.
7. API Key dien dung gia tri `Webhook Secret` trong plugin.

SePay se gui header:

```text
Authorization: Apikey WEBHOOK_SECRET
```

Neu SePay co bo loc noi dung, loc theo prefix chuyen khoan, vi du:

```text
9XQUA
```

Neu dung tinh nang nhan dien ma thanh toan cua SePay:

- Tien to: nhap dung prefix trong plugin, vi du `9XQUA`.
- Hau to: tu 4 ky tu den 10 ky tu.
- La: So nguyen.

Khi webhook match dung ma don va so tien, plugin doi don:

```text
pending -> paid
```

De nut `Gui thu` cua SePay khong bi fail vi payload mau khong co don that, plugin se tra `{"success":true}` cho moi webhook da qua API Key. Cac webhook khong khop don/so tien khong doi trang thai don, nhung van duoc ghi log trong `KDS Manager -> Doi soat`.

Plugin ho tro ca payload BankHub/IPN voi cac truong pho bien nhu `payment_code`, `amount`, `content`, `transfer_type`. Neu `transfer_type` la giao dich tien ra, plugin chi ghi log va khong doi trang thai don. Header xac thuc chap nhan `Authorization: Apikey API_KEY`, `Authorization: Bearer API_KEY`, `Authorization: API_KEY`, hoac `X-Laca-Webhook-Secret`.

Neu khach khong thanh toan trong thoi gian giu QR, don `pending` se tu chuyen sang:

```text
pending -> canceled
```

Neu khach da chuyen tien nhung webhook den muon sau khi don het han, plugin van ghi nhan giao dich hop le va chuyen don ve `paid`.

Luu y quan trong: quet QR chua phai la thanh toan. Khach phai bam xac nhan/chuyen tien thanh cong trong app ngan hang thi SePay moi co giao dich de gui webhook.

Webhook thanh cong tra:

```json
{"success":true}
```

## 4. Cau hinh SpeedSMS

Trong plugin, muc SMS / Zalo ZNS:

```text
Nha cung cap:
SpeedSMS

API Endpoint:
https://api.speedsms.vn/index.php/sms/send

API Key / Token:
API access token tu SpeedSMS

SpeedSMS SMS type:
2

SpeedSMS sender:
de trong khi test voi sms_type = 2. Voi sms_type = 4, neu de trong plugin tu gui sender la Notify.
```

Trong SpeedSMS:

1. Dang nhap `https://connect.speedsms.vn/`.
2. Lay API access token.
3. Nap tien vao tai khoan.
4. Neu dung Brandname, dang ky Brandname truoc, dat sms_type = 3 va nhap dung sender da duyet.

Goi y:

- `sms_type = 2`: CSKH, de test truoc voi sender rong.
- `sms_type = 3`: Brandname, can sender da duoc duyet.
- `sms_type = 4`: Dich vu mac dinh. Thu sender `Notify`, neu loi `sender_not_found` thi thu `Verify`.

Khong can cau hinh webhook SpeedSMS de gui SMS. Webhook SpeedSMS chi dung de nhan trang thai delivered/incoming sau khi SMS da gui thanh cong.

## 4.1. Thay SpeedSMS bang eSMS.vn

Neu SpeedSMS bi loi `sender not found`, co the test eSMS:

```text
Nha cung cap:
eSMS.vn

API Key / Token:
ApiKey tu eSMS

Secret Key:
SecretKey tu eSMS

SMS type:
2 neu dung Brandname, hoac 8 neu dung dau so co dinh/template gia re

Sender / Brandname:
Brandname da dang ky neu SMS type yeu cau
```

Plugin tu dung endpoint eSMS POST JSON:

```text
https://rest.esms.vn/MainService.svc/json/SendMultipleMessage_V4_post_json/
```

eSMS tra `CodeResponse = 100` la thanh cong.

De test SMS nhanh:

1. Vao `KDS Manager -> Cai dat`.
2. Luu cau hinh SMS truoc.
3. O muc `Test gui SMS`, nhap so dien thoai nhan test.
4. Bam `Gui SMS test`.
5. Xem ket qua tren man hinh va vao `KDS Manager -> Tin nhan` de xem request/response.

SMS chi gui khi admin bam `Hoan thanh` trong KDS Manager.

Sau khi bam `Hoan thanh`, vao `KDS Manager -> Tin nhan` de xem log gui SMS/ZNS:

- `sent`: nha cung cap da nhan request thanh cong.
- `error`: token sai, het tien, sai sms_type/sender, so dien thoai khong hop le hoac provider tra loi.
- Voi SpeedSMS, plugin tu doi so `09...` thanh `849...` truoc khi gui.

Noi dung SMS:

```text
[Ten gian hang] thong bao: Don hang #123 cua ban da hoan thanh. Vui long den quay nhan mon nhe!
```

## 5. Test end-to-end

1. Tao mon gia nho, vi du 1000 VND.
2. Vao trang menu, tao don.
3. Chuyen khoan dung so tien va noi dung, vi du `9XQUA0001`.
4. Kiem tra KDS, don phai doi sang `paid`.
5. Neu webhook cham, bam `Da thanh toan` de fallback.
6. Bam `Hoan thanh`.
7. Kiem tra SMS gui ve so dien thoai khach.
8. Vao KDS Manager -> Doi soat de xem log thanh toan.
9. Vao KDS Manager -> Doanh thu de xem tong doanh thu ngay va 7 ngay.

## 6. Van hanh KDS

- KDS tu cap nhat gan realtime theo cau hinh `Toc do cap nhat KDS`.
- Trang `KDS Manager -> Quan ly don` hien tat ca don `Cho thanh toan` va `Da thanh toan` de chu quay xu ly.
- Khi bam `Xac nhan TT`, `Hoan thanh`, hoac `Huy`, giao dien cap nhat nhanh bang AJAX.
- Neu don dang `Cho thanh toan` ma bam `Hoan thanh`, plugin xem nhu hoan thanh ngoai he thong: khong gui SMS va khong tinh doanh thu web.
- Neu don dang `Da thanh toan` ma bam `Hoan thanh`, plugin gui SMS/ZNS va don duoc tinh vao doanh thu.
- Neu bam nham `Hoan thanh` hoac `Huy`, vao khu `Vua xu ly` tren man KDS va bam `Hoan tac`.
- Luu y: neu SMS da gui khi bam `Hoan thanh`, hoan tac chi doi trang thai don, khong thu hoi SMS da gui.
- Trang `Doanh thu` chi tinh don da co xac nhan thanh toan (`paid_at`), ke ca khi sau do don da hoan thanh.
- Trang `Theo doi don` cua khach tu cap nhat moi 2 giay. Neu khach bam `Bat thong bao` va giu trang mo, dien thoai co the rung/hien thong bao khi don duoc bam `Hoan thanh`.
- Thong bao trinh duyet khong thay the SMS/ZNS: neu khach tat trinh duyet, tat quyen thong bao hoac iPhone khong cai PWA, thong bao co the khong hien.
- Web Push/PWA co the gui song song voi SMS khi don da thanh toan duoc bam `Hoan thanh`, nhung can HTTPS va VAPID key trong `Cai dat La Ca`.

## 7. Browser Notification va Web Push/PWA

Browser Notification:

- Hoat dong khi khach dang mo trang `Theo doi don`.
- Khach bam `Bat thong bao`, trinh duyet xin quyen hien thong bao.
- Khi admin bam `Hoan thanh`, trang tu cap nhat sau khoang 2 giay, co the rung/hien thong bao neu thiet bi cho phep.

Web Push/PWA:

- Vao `KDS Manager -> Cai dat La Ca -> Browser Notification / Web Push PWA`.
- Bat `Web Push`.
- Nhap `VAPID subject`, `VAPID public key`, `VAPID private key`.
- Website phai chay HTTPS that tren dien thoai khach.
- Tren iPhone, nen huong dan khach mo trang theo doi bang Safari va `Add to Home Screen` de Web Push on dinh hon.
- Neu Web Push loi, SMS/Zalo ZNS van la kenh du phong quan trong cho su kien dong nguoi.

## 8. Thanh toan tren dien thoai

Khong co mot deep link chung co the mo dung moi app ngan hang cua moi khach. Plugin hien ho tro cach on dinh hon:

- Hien VietQR de khach quet.
- Nut `Mo QR lon` de khach mo anh QR, luu anh, roi quet tu thu vien trong app ngan hang neu app ho tro.
- Nut `Sao chep noi dung CK` de khach nhap tay nhanh khi can.

## 9. Cac loi hay gap

- Don khong doi sang paid: noi dung chuyen khoan khong co dung prefix + ma don.
- SePay khong hien giao dich: kiem tra tien da that su bi tru trong app ngan hang, tai khoan ngan hang nhan tien da ket noi voi SePay, va tai khoan SePay khong o che do test/sandbox.
- SePay unauthorized: API Key tren SePay khong trung Webhook Secret trong plugin.
- Don bi huy som: tang `Thoi gian giu QR` trong Cai dat La Ca.
- SpeedSMS khong gui: token sai, het tien, sai sms_type hoac so dien thoai sai.
- Brandname khong gui: sender chua duoc SpeedSMS duyet.
