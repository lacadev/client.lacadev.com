<?php
/**
 * Shared breadcrumb partial for parent and child themes.
 *
 * @package LacaDevClient
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="breadcumb">
	<div class="container">
		<?php if (function_exists('rank_math_the_breadcrumbs')) : ?>
			<?php rank_math_the_breadcrumbs(); ?>
		<?php endif; ?>
	</div>
</div>
