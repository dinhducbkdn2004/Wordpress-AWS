<?php
/**
 * Trang tĩnh (page). Template Hierarchy:
 * {Template Name tự chọn} → page-{slug}.php → page-{id}.php → page.php → singular.php → index.php
 *
 * @package SalonTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'container container-narrow section' ); ?>>
		<h1 class="page-title"><?php the_title(); ?></h1>
		<div class="entry-content">
			<?php the_content(); ?>
		</div>
	</article>
	<?php
endwhile;

get_footer();
