<?php
/**
 * Trang danh sách dịch vụ: /dich-vu/
 * Template Hierarchy: archive-{post_type}.php → archive.php → index.php
 *
 * @package SalonTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="container section">
	<header class="page-header">
		<h1 class="page-title"><?php post_type_archive_title(); ?></h1>
		<p class="page-description">Bảng giá đã gồm VAT. Thời gian là ước tính, có thể thay đổi theo tình trạng móng.</p>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="card-grid">
			<?php
			// Đây là MAIN QUERY — WP đã tự query salon_service khi parse URL /dich-vu/.
			// Muốn đổi số bài/thứ tự → dùng pre_get_posts trong plugin/theme, KHÔNG tạo WP_Query mới ở đây.
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/card', 'service' );
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
