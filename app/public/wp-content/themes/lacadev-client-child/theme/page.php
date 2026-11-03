<?php
/**
 * App Layout: layouts/app.php
 *
 * This is the template that is used for displaying all pages by default.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WPEmergeTheme
 */

$starts_with_top_hero = function_exists('laca_page_starts_with_top_hero') && laca_page_starts_with_top_hero();
if (!is_front_page() && is_page() && !$starts_with_top_hero):
	echo get_template_part('template-parts/breadcrumb');
endif;

if (is_front_page()):
	the_content();
else:
	?>
	<div class="entry-content">
		<?php
		the_content();
		?>
	</div>
	<?php
endif;
