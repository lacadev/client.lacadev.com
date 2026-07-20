<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Process Steps Block — render.php
 *
 * @package lacadev-client-child
 */

$section_title    = $attributes['sectionTitle']    ?? '';
$steps            = $attributes['steps']           ?? [];

$circle_color      = $attributes['circleColor']     ?? '#0d631b';
$circle_text_color = $attributes['circleTextColor'] ?? '#ffffff';
$title_color       = $attributes['titleColor']      ?? '#263238';
$desc_color        = $attributes['descColor']       ?? '#40493d';
$line_color        = $attributes['lineColor']       ?? '#0d631b';
$section_bg_color  = $attributes['sectionBgColor']  ?? '#eceeec';

$wrapper_attrs = get_block_wrapper_attributes( [
    'class' => 'block-process-steps',
    'style' => sprintf(
        'background-color:%s;',
        esc_attr( $section_bg_color )
    ),
] );
?>

<section <?php echo $wrapper_attrs; ?>>
    <div class="block-process-steps__inner">
        <?php if ( $section_title ) : ?>
            <h2 class="block-process-steps__title" style="color:<?php echo esc_attr( $title_color ); ?>;">
                <?php echo esc_html( $section_title ); ?>
            </h2>
        <?php endif; ?>

        <div class="block-process-steps__row">
            <div class="block-process-steps__connector" style="border-color:<?php echo esc_attr( $line_color ); ?>;"></div>

            <?php foreach ( $steps as $index => $step ) : ?>
                <div class="block-process-steps__step">
                    <div
                        class="block-process-steps__circle"
                        style="background-color:<?php echo esc_attr( $circle_color ); ?>;color:<?php echo esc_attr( $circle_text_color ); ?>;"
                    >
                        <?php echo esc_html( (string) ( $index + 1 ) ); ?>
                    </div>
                    <h5 class="block-process-steps__step-title" style="color:<?php echo esc_attr( $title_color ); ?>;">
                        <?php echo esc_html( $step['title'] ?? '' ); ?>
                    </h5>
                    <p class="block-process-steps__step-desc" style="color:<?php echo esc_attr( $desc_color ); ?>;">
                        <?php echo esc_html( $step['description'] ?? '' ); ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
