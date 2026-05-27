<?php
/**
 * Template Name: Laca Menu App
 *
 * @package LacaSelfOrderingKDS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="site-main laca-menu-template">
	<?php echo do_shortcode( '[laca_menu_app]' ); ?>
</main>

<?php
get_footer();
