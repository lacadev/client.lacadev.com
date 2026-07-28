<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Booking Form Block — render.php
 *
 * @package lacadev-client-child
 */

require_once __DIR__ . '/icons.php';

$section_title       = $attributes['sectionTitle']       ?? '';
$section_description = $attributes['sectionDescription'] ?? '';

$hotline_label = $attributes['hotlineLabel'] ?? '';
$hotline_value = $attributes['hotlineValue'] ?? '';
$address_label = $attributes['addressLabel'] ?? '';
$address_value = $attributes['addressValue'] ?? '';

$service_options    = $attributes['serviceOptions'] ?? [];
$submit_button_text = $attributes['submitButtonText'] ?? '';

// ── Appearance attributes ──────────────────────────────────────────────────
$primary_color = preg_match( '/^#[0-9a-fA-F]{6}$/', $attributes['primaryColor'] ?? '' ) ? $attributes['primaryColor'] : '#0d631b';
$bg_color      = preg_match( '/^#[0-9a-fA-F]{6}$/', $attributes['bgColor'] ?? '' ) ? $attributes['bgColor'] : '#f0f9f0';

$wrapper_attrs = get_block_wrapper_attributes( [
    'id'    => 'dat-lich',
    'class' => 'block-booking-form',
    'style' => sprintf( 'background-color:%s;', esc_attr( $bg_color ) ),
] );
?>

<section <?php echo $wrapper_attrs; ?>>
    <div class="block-booking-form__inner">
        <div class="block-booking-form__info">
            <h2 class="block-booking-form__title"><?php echo esc_html( $section_title ); ?></h2>
            <p class="block-booking-form__description"><?php echo esc_html( $section_description ); ?></p>

            <div class="block-booking-form__contact-list">
                <div class="block-booking-form__contact-item">
                    <span class="block-booking-form__contact-icon" style="background-color:<?php echo esc_attr( $primary_color ); ?>;">
                        <?php echo lcdc_dental_icon( 'call' ); ?>
                    </span>
                    <div class="block-booking-form__contact-text">
                        <p class="block-booking-form__contact-label"><?php echo esc_html( $hotline_label ); ?></p>
                        <p class="block-booking-form__contact-value" style="color:<?php echo esc_attr( $primary_color ); ?>;"><?php echo esc_html( $hotline_value ); ?></p>
                    </div>
                </div>
                <div class="block-booking-form__contact-item">
                    <span class="block-booking-form__contact-icon" style="background-color:<?php echo esc_attr( $primary_color ); ?>;">
                        <?php echo lcdc_dental_icon( 'location_on' ); ?>
                    </span>
                    <div class="block-booking-form__contact-text">
                        <p class="block-booking-form__contact-label"><?php echo esc_html( $address_label ); ?></p>
                        <p class="block-booking-form__contact-value"><?php echo esc_html( $address_value ); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="block-booking-form__card">
            <!-- Form hiện tại chỉ render giao diện — cần nối vào action AJAX submit riêng (xem footer.php làm ví dụ) khi có yêu cầu xử lý thực tế. -->
            <form class="block-booking-form__form">
                <div class="block-booking-form__row">
                    <div class="block-booking-form__field">
                        <label for="booking-form-name"><?php esc_html_e( 'Họ và tên', 'laca' ); ?></label>
                        <input type="text" id="booking-form-name" name="booking_name" placeholder="<?php esc_attr_e( 'Nhập tên của bạn', 'laca' ); ?>" />
                    </div>
                    <div class="block-booking-form__field">
                        <label for="booking-form-phone"><?php esc_html_e( 'Số điện thoại', 'laca' ); ?></label>
                        <input type="tel" id="booking-form-phone" name="booking_phone" placeholder="<?php esc_attr_e( 'Nhập số điện thoại', 'laca' ); ?>" />
                    </div>
                </div>

                <div class="block-booking-form__field block-booking-form__field--full">
                    <label for="booking-form-service"><?php esc_html_e( 'Dịch vụ quan tâm', 'laca' ); ?></label>
                    <select id="booking-form-service" name="booking_service">
                        <?php foreach ( $service_options as $option ) : ?>
                            <option><?php echo esc_html( $option['label'] ?? '' ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="block-booking-form__field block-booking-form__field--full">
                    <label for="booking-form-note"><?php esc_html_e( 'Ghi chú thêm', 'laca' ); ?></label>
                    <textarea id="booking-form-note" name="booking_note" rows="3" placeholder="<?php esc_attr_e( 'Yêu cầu cụ thể của bạn...', 'laca' ); ?>"></textarea>
                </div>

                <button type="submit" class="block-booking-form__submit" style="background-color:<?php echo esc_attr( $primary_color ); ?>;">
                    <?php echo esc_html( $submit_button_text ); ?>
                </button>
            </form>
        </div>
    </div>
</section>
