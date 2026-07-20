<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Clinic Hero Block — render.php
 *
 * @package lacadev-client-child
 */

require_once __DIR__ . '/icons.php';

$badge_text        = $attributes['badgeText']        ?? '';
$headline_line1    = $attributes['headlineLine1']    ?? '';
$headline_line2    = $attributes['headlineLine2']    ?? '';
$checklist_items   = $attributes['checklistItems']   ?? [];

$primary_cta_text   = $attributes['primaryCtaText']   ?? '';
$primary_cta_link   = $attributes['primaryCtaLink']   ?? '#';
$secondary_cta_text = $attributes['secondaryCtaText'] ?? '';
$secondary_cta_link = $attributes['secondaryCtaLink'] ?? '#';

$avatar_image_urls = $attributes['avatarImageUrls'] ?? [];
$rating_text       = $attributes['ratingText']      ?? '';

$hero_image_url = $attributes['heroImageUrl'] ?? '';

// ── Appearance attributes ──────────────────────────────────────────────────
$primary_color  = preg_match( '/^#[0-9a-fA-F]{6}$/', $attributes['primaryColor']  ?? '' ) ? $attributes['primaryColor']  : '#0d631b';
$charcoal_color = preg_match( '/^#[0-9a-fA-F]{6}$/', $attributes['charcoalColor'] ?? '' ) ? $attributes['charcoalColor'] : '#263238';
$bg_color       = preg_match( '/^#[0-9a-fA-F]{6}$/', $attributes['bgColor']       ?? '' ) ? $attributes['bgColor']       : '#f2f4f2';

$wrapper_attrs = get_block_wrapper_attributes( [
    'class' => 'block-clinic-hero',
    'style' => sprintf(
        'background-color:%s;--clinic-hero-primary:%s;--clinic-hero-charcoal:%s;',
        esc_attr( $bg_color ),
        esc_attr( $primary_color ),
        esc_attr( $charcoal_color )
    ),
] );
?>

<section <?php echo $wrapper_attrs; ?>>
    <div class="block-clinic-hero__inner">
        <div class="block-clinic-hero__content">
            <?php if ( $badge_text ) : ?>
                <span class="block-clinic-hero__badge">
                    <?php echo esc_html( $badge_text ); ?>
                </span>
            <?php endif; ?>

            <h1 class="block-clinic-hero__headline" style="color:<?php echo esc_attr( $charcoal_color ); ?>;">
                <?php echo esc_html( $headline_line1 ); ?><br />
                <span class="block-clinic-hero__headline-highlight" style="color:<?php echo esc_attr( $primary_color ); ?>;">
                    <?php echo esc_html( $headline_line2 ); ?>
                </span>
            </h1>

            <?php if ( ! empty( $checklist_items ) ) : ?>
                <ul class="block-clinic-hero__checklist">
                    <?php foreach ( $checklist_items as $item ) : ?>
                        <li class="block-clinic-hero__checklist-item">
                            <span class="block-clinic-hero__checklist-icon" style="color:<?php echo esc_attr( $primary_color ); ?>;"><?php echo lcdc_dental_icon( 'check_circle' ); ?></span>
                            <span class="block-clinic-hero__checklist-text"><?php echo esc_html( $item['text'] ?? '' ); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="block-clinic-hero__cta-group">
                <?php if ( $primary_cta_text ) : ?>
                    <a
                        class="block-clinic-hero__cta block-clinic-hero__cta--primary"
                        href="<?php echo esc_url( $primary_cta_link ); ?>"
                        style="background-color:<?php echo esc_attr( $primary_color ); ?>;"
                    >
                        <?php echo esc_html( $primary_cta_text ); ?>
                    </a>
                <?php endif; ?>
                <?php if ( $secondary_cta_text ) : ?>
                    <a
                        class="block-clinic-hero__cta block-clinic-hero__cta--secondary"
                        href="<?php echo esc_url( $secondary_cta_link ); ?>"
                        style="border-color:<?php echo esc_attr( $primary_color ); ?>;color:<?php echo esc_attr( $primary_color ); ?>;"
                    >
                        <?php echo esc_html( $secondary_cta_text ); ?>
                    </a>
                <?php endif; ?>
            </div>

            <div class="block-clinic-hero__social-proof">
                <?php if ( ! empty( $avatar_image_urls ) ) : ?>
                    <div class="block-clinic-hero__avatars">
                        <?php foreach ( $avatar_image_urls as $avatar_url ) : ?>
                            <?php if ( ! empty( $avatar_url ) ) : ?>
                                <span class="block-clinic-hero__avatar">
                                    <img src="<?php echo esc_url( $avatar_url ); ?>" alt="" />
                                </span>
                            <?php else : ?>
                                <span class="block-clinic-hero__avatar block-clinic-hero__avatar--placeholder"></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="block-clinic-hero__rating">
                    <div class="block-clinic-hero__stars">
                        <?php for ( $i = 0; $i < 5; $i++ ) : ?>
                            <span class="block-clinic-hero__star"><?php echo lcdc_dental_icon( 'star' ); ?></span>
                        <?php endfor; ?>
                    </div>
                    <?php if ( $rating_text ) : ?>
                        <p class="block-clinic-hero__rating-text"><?php echo esc_html( $rating_text ); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="block-clinic-hero__media">
            <span class="block-clinic-hero__glow" style="background-color:<?php echo esc_attr( $primary_color ); ?>;"></span>
            <?php if ( ! empty( $hero_image_url ) ) : ?>
                <img
                    class="block-clinic-hero__image"
                    src="<?php echo esc_url( $hero_image_url ); ?>"
                    alt="<?php echo esc_attr( $headline_line1 . ' ' . $headline_line2 ); ?>"
                />
            <?php endif; ?>
        </div>
    </div>
</section>
