<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Icon inline SVG dùng chung cho các block "Nha Khoa Thiện Phước".
 *
 * Trước đây các block dùng font Material Symbols Outlined (Google Fonts,
 * kiểu ligature — gõ chữ "workspace_premium" rồi font tự thay bằng icon).
 * Cách này phụ thuộc vào việc font tải được từ CDN ngoài — nếu font lỗi/
 * chưa tải kịp, trình duyệt chỉ hiện đúng chữ thô thay vì icon. Chuyển hẳn
 * sang SVG inline để icon luôn hiển thị đúng, không phụ thuộc mạng ngoài.
 *
 * @param string $name    Tên icon (dùng chung tên với Material Symbols cũ
 *                        để không phải đổi lại dữ liệu đã nhập trong block).
 * @param string $classes Class CSS bổ sung cho thẻ <svg>.
 * @return string HTML <svg> đã an toàn (không cần esc thêm ở nơi gọi).
 */
if (!function_exists('lcdc_dental_icon')) {
    function lcdc_dental_icon(string $name, string $classes = ''): string
    {
        $icons = [
            'workspace_premium' => '<circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>',
            'groups' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
            'sentiment_satisfied' => '<circle cx="12" cy="12" r="10"></circle><path d="M8 14s1.5 2 4 2 4-2 4-2"></path><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line>',
            'verified' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline>',
            'clinical_notes' => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>',
            'biotech' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>',
            'sanitizer' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>',
            'account_balance_wallet' => '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line>',
            'check_circle' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>',
            'call' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"></path>',
            'location_on' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle>',
            'mail' => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22 6 12 13 2 6"></polyline>',
            'schedule' => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>',
            'arrow_forward' => '<line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline>',
            'thumb_up' => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>',
            'play_circle' => '<path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"></path><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"></polygon>',
            'chat' => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>',
        ];

        // Gắn kích thước NGAY trên thẻ <svg> (width/height/style="1em") thay
        // vì chỉ trông chờ vào 1 class CSS ngoài (.lcdc-icon) — nếu site nào
        // đó chưa có class này (vd site tham chiếu clients.lacadev.com khi
        // đang test block trực tiếp ở đây, khác với site khách hàng đã có
        // sẵn CSS) thì SVG vẫn hiện đúng kích thước thay vì mất tích/co về
        // kích thước mặc định trình duyệt (300x150px).
        // vertical-align:middle + display:inline-block để tránh bị canh theo
        // "baseline" mặc định của trình duyệt — baseline hay khiến SVG bị
        // đẩy lệch/che khuất phần trên trong 1 số ngữ cảnh layout (đặc biệt
        // gặp trong canvas riêng của trình soạn thảo Gutenberg, khác hẳn
        // frontend), dù không có CSS ngoài (.lcdc-icon) nào định nghĩa lại.
        $size_attrs = 'width="1em" height="1em" style="width:1em;height:1em;display:inline-block;vertical-align:middle;overflow:visible;"';

        // "star" là icon TÔ ĐẶC (đánh giá sao) — khác cấu trúc stroke-outline
        // với các icon còn lại nên render riêng, không đi qua $icons ở trên.
        if ($name === 'star') {
            $cls = trim('lcdc-icon lcdc-icon--star ' . $classes);
            return '<svg class="' . esc_attr($cls) . '" ' . $size_attrs . ' viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">'
                . '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>'
                . '</svg>';
        }

        if (!isset($icons[$name])) {
            return '';
        }

        $cls = trim('lcdc-icon ' . $classes);

        return '<svg class="' . esc_attr($cls) . '" ' . $size_attrs . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . $icons[$name]
            . '</svg>';
    }
}
