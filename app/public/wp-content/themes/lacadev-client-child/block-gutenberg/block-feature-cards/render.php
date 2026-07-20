<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Feature Cards Block — render.php
 * Section lưới thẻ tính năng/dịch vụ — grid thẻ đối xứng, số cột tùy chỉnh.
 * 3 chế độ nguồn nội dung: custom (nhập tay danh sách cố định), manual (chọn
 * tay từng bài trong 1 CPT), auto (query CPT + taxonomy + sắp xếp/ngẫu nhiên).
 *
 * @package lacadev-client-child
 */

require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/post-source-query.php';

// ── Nội dung ────────────────────────────────────────────────────────────────
$section_title    = esc_html( $attributes['sectionTitle']    ?? '' );
$section_subtitle = esc_html( $attributes['sectionSubtitle'] ?? '' );
$mode             = $attributes['mode'] ?? 'custom';
$cta_text_default = $attributes['ctaText'] ?? __( 'Xem chi tiết', 'laca' );

// ── Appearance attributes ──────────────────────────────────────────────────
$bg_color     = preg_match( '/^#[0-9a-fA-F]{6}$/', $attributes['bgColor'] ?? '' ) ? $attributes['bgColor'] : '#ffffff';
$title_color  = preg_match( '/^#[0-9a-fA-F]{6}$/', $attributes['titleColor'] ?? '' ) ? $attributes['titleColor'] : '#263238';
$accent_color = preg_match( '/^#[0-9a-fA-F]{6}$/', $attributes['accentColor'] ?? '' ) ? $attributes['accentColor'] : '#0d631b';
$columns      = max( 2, min( 6, intval( $attributes['columns'] ?? 4 ) ) );

// ── Xây danh sách thẻ theo chế độ ────────────────────────────────────────────
if ( $mode === 'custom' ) {
    $cards = array_map(
        static function ( array $service ) use ( $cta_text_default ): array {
            return [
                'imageUrl'    => $service['imageUrl']    ?? '',
                'title'       => $service['title']       ?? '',
                'description' => $service['description'] ?? '',
                'link'        => $service['link']        ?? '',
                'ctaText'     => $service['ctaText']      ?? $cta_text_default,
            ];
        },
        $attributes['services'] ?? []
    );
} else {
    $query_args = lcdc_build_post_source_query( $attributes, 'post', 4 );
    $loop       = new WP_Query( $query_args );
    $cards      = array_map(
        static function ( WP_Post $post ) use ( $cta_text_default ): array {
            $thumb_id = get_post_thumbnail_id( $post->ID );
            return [
                'imageUrl'    => $thumb_id ? get_the_post_thumbnail_url( $post->ID, 'medium_large' ) : '',
                'title'       => get_the_title( $post ),
                'description' => wp_trim_words( get_the_excerpt( $post ), 20 ),
                'link'        => get_permalink( $post ),
                'ctaText'     => $cta_text_default,
            ];
        },
        $loop->posts
    );
    wp_reset_postdata();
}

if ( empty( $cards ) ) return;

/**
 * Helper: render một thẻ (ảnh + tiêu đề + mô tả + CTA).
 * Dùng function_exists để tránh redeclare khi block xuất hiện nhiều lần trên trang.
 */
if ( ! function_exists( 'lcdc_feature_cards_render_card' ) ) {
    function lcdc_feature_cards_render_card( array $card ): void {
        $image_url   = $card['imageUrl']    ?? '';
        $title       = $card['title']       ?? '';
        $description = $card['description'] ?? '';
        $link        = $card['link']        ?? '';
        $cta_text    = $card['ctaText']     ?? __( 'Xem chi tiết', 'laca' );
        ?>
        <div class="block-feature-cards__card">
            <?php if ( $image_url ) : ?>
                <div class="block-feature-cards__img-wrap">
                    <?php
                    $img = '<img class="block-feature-cards__img" src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $title ) . '" loading="lazy">';
                    if ( $link ) {
                        echo '<a href="' . esc_url( $link ) . '">' . $img . '</a>';
                    } else {
                        echo $img;
                    }
                    ?>
                </div>
            <?php endif; ?>

            <div class="block-feature-cards__content">
                <?php if ( $title ) : ?>
                    <h4 class="block-feature-cards__title"><?php echo esc_html( $title ); ?></h4>
                <?php endif; ?>

                <?php if ( $description ) : ?>
                    <p class="block-feature-cards__desc"><?php echo esc_html( $description ); ?></p>
                <?php endif; ?>

                <?php if ( $cta_text ) : ?>
                    <a class="block-feature-cards__cta" href="<?php echo esc_url( $link ?: '#' ); ?>">
                        <?php echo esc_html( $cta_text ); ?>
                        <span class="block-feature-cards__cta-icon"><?php echo lcdc_dental_icon( 'arrow_forward' ); ?></span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

$wrapper_style = sprintf(
    'background-color:%s;--fc-title-color:%s;--fc-accent-color:%s;--fc-columns:%d;',
    esc_attr( $bg_color ),
    esc_attr( $title_color ),
    esc_attr( $accent_color ),
    $columns
);

$wrapper_attrs = get_block_wrapper_attributes( [
    'class' => 'block-feature-cards',
    'style' => $wrapper_style,
] );
?>

<section <?php echo $wrapper_attrs; ?>>
    <div class="container">
        <div class="block-feature-cards__header" data-aos="fade-up">
            <?php if ( $section_title ) : ?>
                <h2 class="block-feature-cards__heading"><?php echo $section_title; ?></h2>
            <?php endif; ?>
            <?php if ( $section_subtitle ) : ?>
                <p class="block-feature-cards__subheading"><?php echo $section_subtitle; ?></p>
            <?php endif; ?>
        </div>

        <div class="block-feature-cards__grid">
            <?php foreach ( $cards as $card ) : ?>
                <?php lcdc_feature_cards_render_card( $card ); ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
