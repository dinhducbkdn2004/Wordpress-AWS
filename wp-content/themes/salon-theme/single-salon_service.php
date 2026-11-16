<?php
/**
 * Chi tiết một dịch vụ: /dich-vu/son-gel/
 * Template Hierarchy: single-salon_service-{slug}.php → single-salon_service.php → single.php → singular.php → index.php
 *
 * @package SalonTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'container section service-detail' ); ?>>
		<div class="service-media">
			<?php if ( has_post_thumbnail() ) : ?>
				<?php the_post_thumbnail( 'large' ); ?>
			<?php else : ?>
				<div class="placeholder-media" aria-hidden="true"><?php echo esc_html( mb_substr( get_the_title(), 0, 1 ) ); ?></div>
			<?php endif; ?>
		</div>

		<div class="service-body">
			<p class="eyebrow"><a href="<?php echo esc_url( get_post_type_archive_link( 'salon_service' ) ); ?>">← Tất cả dịch vụ</a></p>
			<h1 class="page-title"><?php the_title(); ?></h1>
			<?php salon_theme_service_meta( get_the_ID() ); ?>

			<div class="entry-content">
				<?php the_content(); ?>
			</div>

			<a class="button" href="<?php echo esc_url( salon_theme_booking_url( get_the_ID() ) ); ?>">Đặt lịch dịch vụ này</a>

			<?php if ( is_active_sidebar( 'sidebar-service' ) ) : // không có widget nào → không in thẻ <aside> rỗng ?>
				<aside class="widget-area service-sidebar" aria-label="Thông tin thêm">
					<?php dynamic_sidebar( 'sidebar-service' ); ?>
				</aside>
			<?php endif; ?>
		</div>
	</article>
	<?php
endwhile;

// Dịch vụ khác (secondary query) — loại dịch vụ đang xem.
$related = new WP_Query(
	array(
		'post_type'      => 'salon_service',
		'posts_per_page' => 3,
		'post__not_in'   => array( get_queried_object_id() ),
		'no_found_rows'  => true,
	)
);

if ( $related->have_posts() ) :
	?>
	<section class="section section-alt">
		<div class="container">
			<h2>Dịch vụ khác</h2>
			<div class="card-grid">
				<?php
				while ( $related->have_posts() ) :
					$related->the_post();
					get_template_part( 'template-parts/card', 'service' );
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		</div>
	</section>
	<?php
endif;

get_footer();
