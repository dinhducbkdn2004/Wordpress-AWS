<?php
/**
 * Chi tiết bài viết (post). Template Hierarchy: single-post.php → single.php → singular.php → index.php
 *
 * @package SalonTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'container container-narrow section' ); ?>>
		<header class="entry-header">
			<h1 class="page-title"><?php the_title(); ?></h1>
			<p class="entry-meta">
				<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
			</p>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<div class="entry-thumbnail"><?php the_post_thumbnail( 'large' ); ?></div>
		<?php endif; ?>

		<div class="entry-content">
			<?php the_content(); ?>
		</div>

		<?php
		the_post_navigation(
			array(
				'prev_text' => '← %title',
				'next_text' => '%title →',
			)
		);

		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>
	</article>
	<?php
endwhile;

get_footer();
