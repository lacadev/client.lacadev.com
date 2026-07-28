<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Doctor Team Block — render.php
 * Section "Đội ngũ bác sĩ chuyên môn cao" — ảnh 3:4, tên, chuyên khoa, mô tả ngắn.
 *
 * @package lacadev-client-child
 */

$heading = esc_html( $attributes['sectionTitle'] ?? 'Đội ngũ bác sĩ chuyên môn cao' );

$doctors = $attributes['doctors'] ?? [];

$specialty_color = preg_match( '/^#[0-9a-fA-F]{6}$/', $attributes['specialtyColor'] ?? '' ) ? $attributes['specialtyColor'] : '#0d631b';
$name_color       = preg_match( '/^#[0-9a-fA-F]{6}$/', $attributes['nameColor'] ?? '' ) ? $attributes['nameColor'] : '#263238';
$card_bg_color    = preg_match( '/^#[0-9a-fA-F]{6}$/', $attributes['cardBgColor'] ?? '' ) ? $attributes['cardBgColor'] : '#ffffff';
$border_color     = preg_match( '/^#[0-9a-fA-F]{6}$/', $attributes['borderColor'] ?? '' ) ? $attributes['borderColor'] : '#bfcaba';
?>

<section <?php echo get_block_wrapper_attributes( [ 'class' => 'block-doctor-team' ] ); ?>>
    <div class="block-doctor-team__inner">

        <?php if ( $heading ) : ?>
            <div class="block-doctor-team__header" data-aos="fade-up">
                <h2 class="block-doctor-team__heading"><?php echo $heading; ?></h2>
            </div>
        <?php endif; ?>

        <div class="block-doctor-team__grid">
            <?php foreach ( $doctors as $doctor ) : ?>
                <div
                    class="block-doctor-team__card"
                    style="background:<?php echo esc_attr( $card_bg_color ); ?>;border-color:<?php echo esc_attr( $border_color ); ?>;"
                    data-aos="fade-up"
                >
                    <div class="block-doctor-team__photo">
                        <?php if ( ! empty( $doctor['imageUrl'] ) ) : ?>
                            <img
                                src="<?php echo esc_url( $doctor['imageUrl'] ); ?>"
                                alt="<?php echo esc_attr( $doctor['name'] ?? '' ); ?>"
                                loading="lazy"
                            />
                        <?php else : ?>
                            <div class="block-doctor-team__no-image"></div>
                        <?php endif; ?>
                    </div>

                    <div class="block-doctor-team__info">
                        <?php if ( ! empty( $doctor['name'] ) ) : ?>
                            <h4 class="block-doctor-team__name" style="color:<?php echo esc_attr( $name_color ); ?>;">
                                <?php echo esc_html( $doctor['name'] ); ?>
                            </h4>
                        <?php endif; ?>

                        <?php if ( ! empty( $doctor['specialty'] ) ) : ?>
                            <p class="block-doctor-team__specialty" style="color:<?php echo esc_attr( $specialty_color ); ?>;">
                                <?php echo esc_html( $doctor['specialty'] ); ?>
                            </p>
                        <?php endif; ?>

                        <?php if ( ! empty( $doctor['bio'] ) ) : ?>
                            <p class="block-doctor-team__bio">
                                <?php echo esc_html( $doctor['bio'] ); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</section>
