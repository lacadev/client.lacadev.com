<?php
/**
 * Template Name: Laca Pickup Screen
 *
 * @package LacaSelfOrderingKDS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="site-main laca-pickup-screen-template">
	<?php echo do_shortcode( '[laca_pickup_screen]' ); ?>
</main>

<?php
get_footer();
