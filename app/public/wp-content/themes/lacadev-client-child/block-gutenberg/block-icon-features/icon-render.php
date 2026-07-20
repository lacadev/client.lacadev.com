<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * icon-render.php — bản tham chiếu dùng chung, render 1 icon do admin tự
 * cung cấp (dán SVG code hoặc tải ảnh), thay cho cách tra tên icon theo 1 bộ
 * SVG tự vẽ giới hạn (không khớp icon thật của Material Symbols).
 *
 * QUAN TRỌNG: KHÔNG require file này qua '../utils/icon-render.php'. Cơ chế
 * Block Marketplace sync chỉ đóng gói ĐÚNG 1 thư mục block — mỗi block cần
 * dùng phải COPY nguyên file này vào thư mục của chính nó rồi
 * require_once __DIR__ . '/icon-render.php'. Xem RULE_BLOCK_GUTENBERG.md.
 *
 * Contract dữ liệu (cùng với utils/icon-input.js):
 *   $icon = [ 'type' => 'svg'|'image', 'svg' => string, 'imageId' => int, 'imageUrl' => string ]
 */

if ( ! function_exists( 'lcdc_sanitize_icon_svg' ) ) {
    /**
     * Lọc SVG do admin dán tay — chỉ giữ thẻ/thuộc tính hình học cơ bản, bỏ
     * hẳn width/height/style trên thẻ <svg> gốc để ép icon luôn theo đúng
     * kích thước 1em do wrapper ngoài quy định (xem lcdc_render_icon()).
     */
    function lcdc_sanitize_icon_svg( string $svg ): string {
        $allowed = [
            'svg'      => [ 'viewbox' => true, 'xmlns' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'aria-hidden' => true, 'focusable' => true, 'role' => true ],
            'g'        => [ 'fill' => true, 'stroke' => true, 'transform' => true ],
            'path'     => [ 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'fill-rule' => true ],
            'circle'   => [ 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ],
            'ellipse'  => [ 'cx' => true, 'cy' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true ],
            'rect'     => [ 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true ],
            'line'     => [ 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true, 'stroke-width' => true ],
            'polyline' => [ 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ],
            'polygon'  => [ 'points' => true, 'fill' => true, 'stroke' => true ],
            'title'    => [],
        ];

        return wp_kses( $svg, $allowed );
    }
}

if ( ! function_exists( 'lcdc_render_icon' ) ) {
    /**
     * @param array  $icon    Shape { type, svg, imageId, imageUrl } (xem contract phía trên).
     * @param string $classes Class CSS bổ sung cho thẻ bọc ngoài.
     * @return string HTML đã an toàn (không cần esc thêm ở nơi gọi).
     */
    function lcdc_render_icon( $icon, string $classes = '' ): string {
        if ( ! is_array( $icon ) ) {
            return ''; // Dữ liệu cũ (chuỗi tên icon trước khi đổi sang dạng object) — bỏ qua, admin cần chọn lại icon.
        }

        $type = $icon['type'] ?? 'svg';
        $cls  = trim( 'lcdc-icon ' . $classes );

        if ( $type === 'image' ) {
            $image_url = $icon['imageUrl'] ?? '';
            if ( ! $image_url ) {
                return '';
            }
            return sprintf(
                '<img class="%s" src="%s" alt="" style="width:1em;height:1em;object-fit:contain;display:inline-block;vertical-align:middle;" />',
                esc_attr( $cls ),
                esc_url( $image_url )
            );
        }

        $svg_raw = $icon['svg'] ?? '';
        if ( ! $svg_raw ) {
            return '';
        }

        $svg_clean = lcdc_sanitize_icon_svg( $svg_raw );
        if ( ! $svg_clean ) {
            return '';
        }

        return sprintf(
            '<span class="%s" style="display:inline-flex;width:1em;height:1em;overflow:visible;vertical-align:middle;">%s</span>',
            esc_attr( $cls ),
            $svg_clean
        );
    }
}
