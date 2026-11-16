<?php
/**
 * Template dự phòng CUỐI CÙNG của Template Hierarchy — file bắt buộc của classic theme.
 * Dùng khi không có template cụ thể hơn (vd trang blog, tìm kiếm, category).
 *
 * @package SalonTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="container section">
	<header class="page-header">
		<h1 class="page-title">
			<?php
			if ( is_search() ) {
				/* translators: %s: từ khóa tìm kiếm */
				printf( 'Kết quả cho: “%s”', esc_html( get_search_query() ) );
			} elseif ( is_archive() ) {
				the_archive_title();
			} else {
				echo esc_html( is_home() && ! is_front_page() ? single_post_title( '', false ) : 'Tin tức' );
			}
			?>
		</h1>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="post-list">
			<?php
			// THE LOOP: duyệt các bài trong MAIN QUERY ($wp_query) mà WP đã chạy ở bước wp().
			while ( have_posts() ) :
				the_post(); // đặt global $post = bài hiện tại → the_title(), the_permalink() dùng được
				get_template_part( 'template-parts/content', get_post_type() ); // content-post.php → content.php
			endwhile;
			?>
		</div>

		<?php the_posts_pagination( array( 'mid_size' => 1 ) ); ?>
	<?php else : ?>
		<?php get_template_part( 'template-parts/content', 'none' ); ?>
	<?php endif; ?>
</div>

<?php
get_footer();
