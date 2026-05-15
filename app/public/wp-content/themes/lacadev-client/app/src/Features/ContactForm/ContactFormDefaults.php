<?php

namespace App\Features\ContactForm;

/**
 * Default form fields and email templates used when creating a new contact form.
 */
final class ContactFormDefaults
{
    public static function rows(): array
    {
        return [
            [
                'id' => 'row_default_1',
                'cols' => [
                    [
                        'id' => 'col_d1',
                        'span' => 6,
                        'fields' => [
                            ['id' => 'fd_name', 'type' => 'text', 'name' => 'name', 'label' => 'Họ và tên', 'placeholder' => 'Họ và tên của bạn', 'required' => true, 'options' => []],
                        ],
                    ],
                    [
                        'id' => 'col_d2',
                        'span' => 6,
                        'fields' => [
                            ['id' => 'fd_phone', 'type' => 'phone', 'name' => 'phone_number', 'label' => 'Số điện thoại', 'placeholder' => '09xx xxx xxx', 'required' => true, 'options' => []],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'row_default_2',
                'cols' => [
                    [
                        'id' => 'col_d3',
                        'span' => 12,
                        'fields' => [
                            ['id' => 'fd_email', 'type' => 'email', 'name' => 'email', 'label' => 'Email liên hệ', 'placeholder' => 'email@example.com (Không bắt buộc)', 'required' => false, 'options' => []],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'row_default_3',
                'cols' => [
                    [
                        'id' => 'col_d4',
                        'span' => 12,
                        'fields' => [
                            ['id' => 'fd_msg', 'type' => 'textarea', 'name' => 'message', 'label' => 'Nội dung', 'placeholder' => 'Ý tưởng hoặc lời nhắn gửi...', 'required' => true, 'options' => []],
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function adminEmailBody(): string
    {
        return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:40px 20px;background:#ffffff;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;color:#111111;line-height:1.6">
  <div style="max-width:540px;margin:0 auto;border:1px solid #e5e5e5;padding:40px">
    <div style="margin-bottom:30px">
      <h1 style="margin:0 0 5px;font-size:20px;font-weight:600;letter-spacing:-0.5px">Thông báo liên hệ mới</h1>
      <p style="margin:0;font-size:13px;color:#666666">$time - $date</p>
    </div>
    <div style="margin-bottom:30px;padding-bottom:30px;border-bottom:1px solid #eeeeee">
      <p style="margin:0 0 10px;font-size:14px"><strong>Người gửi:</strong> $name</p>
      <p style="margin:0 0 10px;font-size:14px"><strong>Số điện thoại:</strong> $phone_number</p>
      <p style="margin:0;font-size:14px"><strong>Email:</strong> $email</p>
    </div>
    <div style="margin-bottom:40px">
      <p style="margin:0 0 10px;font-size:12px;color:#888888;text-transform:uppercase;letter-spacing:0.5px">Nội dung</p>
      <p style="margin:0;white-space:pre-wrap;font-size:15px;line-height:1.7;color:#333333">$message</p>
    </div>
    <div style="margin-top:40px;padding-top:20px;border-top:1px solid #eeeeee">
      <p style="margin:0;font-size:12px;color:#999999">IP: $ip</p>
    </div>
  </div>
</body>
</html>';
    }

    public static function customerEmailBody(string $siteName): string
    {
        return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:40px 20px;background:#ffffff;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;color:#111111;line-height:1.6">
  <div style="max-width:540px;margin:0 auto;border:1px solid #e5e5e5;padding:40px">
    <div style="margin-bottom:30px">
      <h1 style="margin:0 0 5px;font-size:20px;font-weight:600;letter-spacing:-0.5px">Đã nhận lời nhắn</h1>
      <p style="margin:0;font-size:13px;color:#666666">Cảm ơn bạn đã liên hệ với ' . esc_html($siteName) . '</p>
    </div>
    <div style="margin-bottom:30px">
      <p style="margin:0 0 15px;font-size:15px">Chào <strong>$name</strong>,</p>
      <p style="margin:0;font-size:15px;color:#444444">Tôi đã nhận được tin nhắn cùng số điện thoại <strong>$phone_number</strong> của bạn.</p>
      <p style="margin:10px 0 0;font-size:15px;color:#444444">Tôi sẽ xem xét và phản hồi trong vòng 24 giờ.</p>
    </div>
    <div style="margin-bottom:30px;padding:25px;background:#fafafa;border:1px solid #eeeeee">
      <p style="margin:0 0 10px;font-size:12px;color:#888888;text-transform:uppercase;letter-spacing:0.5px">Tóm tắt nội dung</p>
      <p style="margin:0;font-size:14px;color:#555555">"$message"</p>
    </div>
    <div style="margin-top:40px;padding-top:20px;border-top:1px solid #eeeeee">
      <p style="margin:0;font-size:12px;color:#999999">Đây là email xác nhận tự động từ ' . esc_html($siteName) . '.</p>
    </div>
  </div>
</body>
</html>';
    }
}
