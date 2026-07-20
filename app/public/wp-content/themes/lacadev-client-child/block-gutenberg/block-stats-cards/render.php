<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Stats Cards Block — render.php
 * Lưới thẻ thống kê (icon + số liệu + nhãn) dùng chung cho nhiều loại hình kinh doanh.
 *
 * @package lacadev-client-child
 */

require_once __DIR__ . '/icon-render.php';

// ── Nội dung ────────────────────────────────────────────────────────────────
$items = $attributes['items'] ?? [];

// ── Appearance attributes ──────────────────────────────────────────────────
$icon_color      = $attributes['iconColor']     ?? '#0d631b';
$number_color    = $attributes['numberColor']   ?? '#263238';
$label_color     = $attributes['labelColor']    ?? '#40493d';
$card_bg_color   = $attributes['cardBgColor']   ?? '#ffffff';
$bg_color        = $attributes['bgColor']       ?? 'transparent';
$pull_up_overlap = max( 0, min( 100, intval( $attributes['pullUpOverlap'] ?? 60 ) ) );

// Hiệu ứng "kéo lên đè lên section trên" (margin-top âm) chỉ đúng ý đồ
// thiết kế ở FRONTEND thật, nơi block phía trên nằm đúng vị trí liền kề.
// Trong khung soạn thảo Gutenberg, ServerSideRender gọi render.php này qua
// REST API — các block ở đó xếp riêng biệt (không cùng luồng tài liệu như
// frontend), nên margin âm sẽ đẩy card chui xuống DƯỚI/bị che bởi block
// phía trên, trông như icon bị cắt mất. Phát hiện ngữ cảnh REST (editor)
// bằng REST_REQUEST và tắt hẳn margin âm trong trường hợp đó.
$is_editor_context = defined( 'REST_REQUEST' ) && REST_REQUEST;
$margin_top_css    = $is_editor_context ? '0' : ( '-' . $pull_up_overlap . 'px' );

$wrapper_attrs = get_block_wrapper_attributes( [
    'class' => 'block-stats-cards',
    'style' => sprintf(
        'background-color:%s;margin-top:%s;',
        esc_attr( $bg_color ),
        esc_attr( $margin_top_css )
    ),
] );
?>

<section <?php echo $wrapper_attrs; ?>>
    <div class="block-stats-cards__grid">
        <?php foreach ( $items as $item ) :
            $icon        = $item['icon'] ?? null;
            $item_number = $item['number'] ?? '0';
            $item_suffix = $item['suffix'] ?? '';
            $item_label  = $item['label']  ?? '';
        ?>
            <div
                class="block-stats-cards__card"
                style="background-color:<?php echo esc_attr( $card_bg_color ); ?>;"
            >
                <span
                    class="block-stats-cards__icon"
                    style="color:<?php echo esc_attr( $icon_color ); ?>;"
                ><?php echo lcdc_render_icon( $icon ); ?></span>
                <h3
                    class="block-stats-cards__number"
                    style="color:<?php echo esc_attr( $number_color ); ?>;"
                ><?php echo esc_html( $item_number ); ?><?php echo esc_html( $item_suffix ); ?></h3>
                <p
                    class="block-stats-cards__label"
                    style="color:<?php echo esc_attr( $label_color ); ?>;"
                ><?php echo esc_html( $item_label ); ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>
