<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Testimonials Block — render.php
 *
 * @package lacadev-client-child
 */

require_once __DIR__ . '/icons.php';

$section_title  = $attributes['sectionTitle'] ?? '';
$testimonials   = $attributes['testimonials'] ?? [];

$star_color     = $attributes['starColor']     ?? '#0d631b';
$quote_color    = $attributes['quoteColor']    ?? '#40493d';
$name_color     = $attributes['nameColor']     ?? '#263238';
$location_color = $attributes['locationColor'] ?? '#78716c';
$card_bg_color  = $attributes['cardBgColor']   ?? '#ffffff';
$border_color   = $attributes['borderColor']   ?? '#e1e3e1';

$wrapper_attrs = get_block_wrapper_attributes( [
    'class' => 'block-testimonials',
] );
?>

<section <?php echo $wrapper_attrs; ?>>
    <div class="block-testimonials__inner">
        <?php if ( ! empty( $section_title ) ) : ?>
            <h2 class="block-testimonials__title"><?php echo esc_html( $section_title ); ?></h2>
        <?php endif; ?>

        <div class="block-testimonials__grid">
            <?php foreach ( $testimonials as $testimonial ) :
                $quote      = $testimonial['quote']         ?? '';
                $name       = $testimonial['name']           ?? '';
                $location   = $testimonial['location']       ?? '';
                $avatar_url = $testimonial['avatarImageUrl'] ?? '';
                $rating     = min( 5, max( 1, (int) ( $testimonial['rating'] ?? 5 ) ) );
                ?>
                <div
                    class="block-testimonials__card"
                    style="background-color:<?php echo esc_attr( $card_bg_color ); ?>;border-color:<?php echo esc_attr( $border_color ); ?>;"
                >
                    <div class="block-testimonials__stars" style="color:<?php echo esc_attr( $star_color ); ?>;">
                        <?php for ( $i = 0; $i < $rating; $i++ ) : ?>
                            <?php echo lcdc_dental_icon( 'star' ); ?>
                        <?php endfor; ?>
                    </div>

                    <?php if ( ! empty( $quote ) ) : ?>
                        <p class="block-testimonials__quote" style="color:<?php echo esc_attr( $quote_color ); ?>;">&ldquo;<?php echo nl2br( esc_html( $quote ) ); ?>&rdquo;</p>
                    <?php endif; ?>

                    <div class="block-testimonials__author">
                        <?php if ( ! empty( $avatar_url ) ) : ?>
                            <img
                                class="block-testimonials__avatar"
                                src="<?php echo esc_url( $avatar_url ); ?>"
                                alt="<?php echo esc_attr( $name ); ?>"
                                loading="lazy"
                            />
                        <?php else : ?>
                            <div class="block-testimonials__avatar block-testimonials__avatar--placeholder"></div>
                        <?php endif; ?>

                        <div class="block-testimonials__author-info">
                            <?php if ( ! empty( $name ) ) : ?>
                                <span class="block-testimonials__name" style="color:<?php echo esc_attr( $name_color ); ?>;"><?php echo esc_html( $name ); ?></span>
                            <?php endif; ?>
                            <?php if ( ! empty( $location ) ) : ?>
                                <span class="block-testimonials__location" style="color:<?php echo esc_attr( $location_color ); ?>;"><?php echo esc_html( $location ); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
