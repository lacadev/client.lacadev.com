<?php
/**
 * Shared product result card for parent search fallback.
 *
 * @package LacaDevClient
 */

if (!defined('ABSPATH')) {
	exit;
}

$product = function_exists('wc_get_product') ? wc_get_product(get_the_ID()) : null;
$price   = $product ? $product->get_price_html() : '';
?>
<article <?php post_class('loop-product'); ?>>
	<a href="<?php the_permalink(); ?>" class="loop-product__link">
		<?php if (has_post_thumbnail()) : ?>
			<div class="loop-product__thumb">
				<?php the_post_thumbnail('medium', ['loading' => 'lazy']); ?>
			</div>
		<?php endif; ?>

		<div class="loop-product__content">
			<h3 class="loop-product__title"><?php the_title(); ?></h3>

			<?php if ($price) : ?>
				<div class="loop-product__price"><?php echo wp_kses_post($price); ?></div>
			<?php endif; ?>

			<?php if (has_excerpt()) : ?>
				<div class="loop-product__excerpt"><?php echo esc_html(get_the_excerpt()); ?></div>
			<?php endif; ?>
		</div>
	</a>
</article>
