<?php
/**
 * Template Name: Laca Order Status
 *
 * @package LacaSelfOrderingKDS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="site-main laca-order-status-template">
	<?php echo do_shortcode( '[laca_order_status]' ); ?>
</main>

<?php
get_footer();
