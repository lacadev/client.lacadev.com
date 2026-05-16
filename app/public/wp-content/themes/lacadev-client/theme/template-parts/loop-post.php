<?php
/**
 * Shared search/archive card for generic posts/pages.
 *
 * @package LacaDevClient
 */

if (!defined('ABSPATH')) {
	exit;
}

global $post;

$post_id       = $post->ID;
$url           = get_the_permalink($post_id);
$thumbnail     = getResponsivePostThumbnail($post_id);
$title         = get_the_title($post_id);
$excerpt       = get_the_excerpt($post_id);
?>
<div class="loop-service">
	<a href="<?php echo esc_url($url); ?>">
		<div class="inner">
			<figure>
				<?php echo $thumbnail; ?>
			</figure>

			<div class="content">
				<?php if ($title) : ?>
					<h3 class="heading"><?php echo esc_html($title); ?></h3>
				<?php endif; ?>

				<?php if ($excerpt) : ?>
					<div class="desc"><?php echo esc_html($excerpt); ?></div>
				<?php endif; ?>
			</div>
		</div>
	</a>
</div>
