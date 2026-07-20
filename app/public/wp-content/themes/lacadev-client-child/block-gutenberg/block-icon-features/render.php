<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Icon Features Block — render.php
 * Lưới thẻ lý do/ưu điểm nổi bật kèm icon, dùng chung cho nhiều loại hình kinh doanh.
 *
 * @package lacadev-client-child
 */

require_once __DIR__ . '/icon-render.php';

// ── Nội dung ────────────────────────────────────────────────────────────────
$section_title = $attributes['sectionTitle'] ?? '';
$items          = $attributes['items']       ?? [];

// ── Appearance attributes ──────────────────────────────────────────────────
$icon_color       = $attributes['iconColor']     ?? '#0d631b';
$icon_bg_color    = $attributes['iconBgColor']   ?? '#e8f5e9';
$title_color      = $attributes['titleColor']    ?? '#263238';
$desc_color       = $attributes['descColor']     ?? '#40493d';
$card_bg_color    = $attributes['cardBgColor']   ?? '#ffffff';
$section_bg_color = $attributes['sectionBgColor'] ?? '#f2f4f2';

$wrapper_attrs = get_block_wrapper_attributes( [
    'class' => 'block-icon-features',
    'style' => sprintf(
        'background-color:%s;',
        esc_attr( $section_bg_color )
    ),
] );
?>

<section <?php echo $wrapper_attrs; ?>>
    <div class="block-icon-features__inner">
        <?php if ( $section_title ) : ?>
            <h2 class="block-icon-features__title" style="color:<?php echo esc_attr( $title_color ); ?>;">
                <?php echo esc_html( $section_title ); ?>
            </h2>
        <?php endif; ?>

        <div class="block-icon-features__grid">
            <?php foreach ( $items as $item ) :
                $icon        = $item['icon'] ?? null;
                $item_title  = $item['title']       ?? '';
                $item_desc   = $item['description'] ?? '';
            ?>
                <div
                    class="block-icon-features__card"
                    style="background-color:<?php echo esc_attr( $card_bg_color ); ?>;"
                >
                    <div
                        class="block-icon-features__icon-wrap"
                        style="background-color:<?php echo esc_attr( $icon_bg_color ); ?>;"
                    >
                        <span
                            class="block-icon-features__icon"
                            style="color:<?php echo esc_attr( $icon_color ); ?>;"
                        ><?php echo lcdc_render_icon( $icon ); ?></span>
                    </div>
                    <h3
                        class="block-icon-features__card-title"
                        style="color:<?php echo esc_attr( $title_color ); ?>;"
                    ><?php echo esc_html( $item_title ); ?></h3>
                    <p
                        class="block-icon-features__card-desc"
                        style="color:<?php echo esc_attr( $desc_color ); ?>;"
                    ><?php echo esc_html( $item_desc ); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
