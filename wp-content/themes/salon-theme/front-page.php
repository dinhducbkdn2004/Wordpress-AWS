<?php
/**
 * Trang chủ. Template Hierarchy: front-page.php được ưu tiên cho trang chủ
 * bất kể Settings → Reading đang chọn "bài viết mới nhất" hay "một trang tĩnh".
 *
 * @package SalonTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<section class="hero">
	<div class="container hero-inner">
		<p class="eyebrow">Nail &amp; Beauty Salon</p>
		<h1 class="hero-title"><?php bloginfo( 'name' ); ?></h1>
		<p class="hero-lead">
			<?php
			$tagline = get_bloginfo( 'description' );
			echo esc_html( $tagline ? $tagline : 'Bộ móng đẹp, tâm trạng đẹp. Đặt lịch online trong 1 phút, không phải chờ.' );
			?>
		</p>
		<div class="hero-actions">
			<a class="button" href="<?php echo esc_url( salon_theme_booking_url() ); ?>">Đặt lịch ngay</a>
			<?php if ( get_post_type_archive_link( 'salon_service' ) ) : ?>
				<a class="button button-outline" href="<?php echo esc_url( get_post_type_archive_link( 'salon_service' ) ); ?>">Xem dịch vụ</a>
			<?php endif; ?>
		</div>
	</div>
</section>

<?php
// SECONDARY QUERY: main query của trang chủ là "bài viết blog" → muốn lấy dịch vụ phải tạo WP_Query riêng.
$services = new WP_Query(
	array(
		'post_type'      => 'salon_service',
		'posts_per_page' => 6,
		'no_found_rows'  => true, // không phân trang → bỏ câu đếm SQL_CALC_FOUND_ROWS (Phần 2)
	)
);
?>

<?php if ( $services->have_posts() ) : ?>
	<section class="section">
		<div class="container">
			<header class="section-header">
				<h2>Dịch vụ nổi bật</h2>
				<p>Sản phẩm chính hãng, dụng cụ tiệt trùng sau mỗi khách.</p>
			</header>

			<div class="card-grid">
				<?php
				while ( $services->have_posts() ) :
					$services->the_post(); // gọi trên $services, KHÔNG phải the_post() toàn cục
					get_template_part( 'template-parts/card', 'service' );
				endwhile;
				wp_reset_postdata(); // BẮT BUỘC: trả global $post về main query
				?>
			</div>
		</div>
	</section>
<?php endif; ?>

<section class="section section-alt">
	<div class="container features">
		<div class="feature">
			<h3>Đặt lịch online</h3>
			<p>Chọn dịch vụ, nhân viên và giờ phù hợp — không cần gọi điện.</p>
		</div>
		<div class="feature">
			<h3>Thợ tay nghề cao</h3>
			<p>Kỹ thuật viên trên 3 năm kinh nghiệm, cập nhật mẫu mới mỗi tháng.</p>
		</div>
		<div class="feature">
			<h3>Vệ sinh chuẩn y tế</h3>
			<p>Dụng cụ hấp sấy tiệt trùng, giũa và bông dùng một lần.</p>
		</div>
	</div>
</section>

<?php
$staff = new WP_Query(
	array(
		'post_type'      => 'salon_staff',
		'posts_per_page' => 4,
		'no_found_rows'  => true,
		'orderby'        => 'title',
		'order'          => 'ASC',
	)
);
?>

<?php if ( $staff->have_posts() ) : ?>
	<section class="section">
		<div class="container">
			<header class="section-header">
				<h2>Đội ngũ của chúng tôi</h2>
			</header>
			<div class="staff-grid">
				<?php
				while ( $staff->have_posts() ) :
					$staff->the_post();
					?>
					<article class="staff-card">
						<div class="avatar" aria-hidden="true">
							<?php
							if ( has_post_thumbnail() ) {
								the_post_thumbnail( 'thumbnail' );
							} else {
								// Không có ảnh → chữ cái đầu của TÊN (từ cuối): "Chị Lan" → "L".
								$words = explode( ' ', trim( wp_strip_all_tags( get_the_title() ) ) );
								echo esc_html( mb_substr( end( $words ), 0, 1 ) );
							}
							?>
						</div>
						<h3><?php the_title(); ?></h3>
						<?php if ( has_excerpt() ) : ?>
							<p><?php echo esc_html( get_the_excerpt() ); ?></p>
						<?php endif; ?>
					</article>
					<?php
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		</div>
	</section>
<?php endif; ?>

<section class="cta-band">
	<div class="container cta-inner">
		<h2>Sẵn sàng cho bộ móng mới?</h2>
		<a class="button button-light" href="<?php echo esc_url( salon_theme_booking_url() ); ?>">Đặt lịch ngay</a>
	</div>
</section>

<?php
get_footer();
