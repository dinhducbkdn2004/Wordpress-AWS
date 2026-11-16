<?php
/**
 * Hàm hiển thị DÙNG CHUNG cho shortcode [salon_services] và block salon/featured-services.
 *
 * Một nguồn HTML duy nhất → sửa giao diện danh sách 1 chỗ, shortcode và block không bao giờ lệch nhau.
 * Hàm TRẢ VỀ chuỗi (không echo) vì shortcode callback và render_callback đều phải return.
 *
 * @package SalonBooking
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'salon_register_frontend_assets' );

/**
 * Chỉ ĐĂNG KÝ (register), chưa nạp. Ai cần thì enqueue:
 *  - block: block.json "style": "salon-frontend" → WP tự enqueue khi block xuất hiện
 *  - shortcode / widget: tự gọi wp_enqueue_style() lúc render
 */
function salon_register_frontend_assets() {
	$path = 'assets/css/frontend.css';
	wp_register_style(
		'salon-frontend',
		plugins_url( $path, SALON_FILE ),
		array(),
		(string) filemtime( plugin_dir_path( SALON_FILE ) . $path )
	);
}

/**
 * Chuẩn hoá tham số đầu vào (từ shortcode, block attribute...) về kiểu và khoảng hợp lệ.
 *
 * @param array $args Tham số thô.
 * @return array{limit:int,columns:int,category:string,show_price:bool,exclude:int}
 */
function salon_normalize_services_args( array $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'limit'      => 6,
			'columns'    => 3,
			'category'   => '',
			'show_price' => true,
			'exclude'    => 0,
		)
	);

	return array(
		'limit'      => max( 1, min( 12, absint( $args['limit'] ) ) ),   // 1..12: chặn ai đó nhập limit="10000"
		'columns'    => max( 1, min( 4, absint( $args['columns'] ) ) ),  // 1..4 (0/"abc" → 1)
		'category'   => sanitize_title( $args['category'] ),             // slug: chỉ a-z 0-9 -
		'show_price' => (bool) $args['show_price'],
		'exclude'    => absint( $args['exclude'] ),
	);
}

/**
 * HTML danh sách dịch vụ.
 *
 * @param array $args Xem salon_normalize_services_args().
 * @return string
 */
function salon_render_services( array $args = array() ) {
	$args = salon_normalize_services_args( $args );

	$query_args = array(
		'post_type'           => 'salon_service',
		'post_status'         => 'publish',
		'posts_per_page'      => $args['limit'],
		'orderby'             => array(
			'menu_order' => 'ASC',  // ô "Order" (supports page-attributes) — admin tự sắp
			'date'       => 'DESC',
		),
		'no_found_rows'       => true, // không phân trang → bỏ câu đếm
		'ignore_sticky_posts' => true,
	);
	if ( $args['category'] ) {
		$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'salon_service_cat',
				'field'    => 'slug',
				'terms'    => $args['category'],
			),
		);
	}
	if ( $args['exclude'] ) {
		$query_args['post__not_in'] = array( $args['exclude'] );
	}

	/**
	 * Cho theme/plugin khác chỉnh query (vd chỉ lấy dịch vụ có ảnh).
	 *
	 * @param array $query_args Tham số WP_Query.
	 * @param array $args       Tham số đã chuẩn hoá.
	 */
	$query = new WP_Query( apply_filters( 'salon_services_query_args', $query_args, $args ) );

	if ( ! $query->have_posts() ) {
		return '<p class="salon-services__empty">Chưa có dịch vụ nào.</p>';
	}

	$booking_url = function_exists( 'salon_get_booking_url' ) ? salon_get_booking_url() : '';

	ob_start(); // viết HTML dễ đọc như template, rồi lấy ra thành chuỗi
	?>
	<ul class="salon-services salon-services--cols-<?php echo (int) $args['columns']; ?>">
		<?php
		foreach ( $query->posts as $service ) : // dùng $query->posts thay vì the_post(): không đụng global $post → không cần wp_reset_postdata
			$details = salon_get_service_details( $service->ID );
			$link    = get_permalink( $service );
			?>
			<li class="salon-services__item">
				<a class="salon-services__media" href="<?php echo esc_url( $link ); ?>" tabindex="-1" aria-hidden="true">
					<?php
					if ( has_post_thumbnail( $service ) ) {
						echo get_the_post_thumbnail( $service, 'medium_large', array( 'loading' => 'lazy' ) );
					} else {
						echo '<span class="salon-services__placeholder">' . esc_html( mb_substr( get_the_title( $service ), 0, 1 ) ) . '</span>';
					}
					?>
				</a>
				<div class="salon-services__body">
					<h3 class="salon-services__title"><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( get_the_title( $service ) ); ?></a></h3>
					<?php if ( $args['show_price'] ) : ?>
						<p class="salon-services__meta">
							<strong><?php echo esc_html( salon_format_price( $details['price'] ) ); ?></strong>
							<span>· <?php echo esc_html( $details['duration'] ); ?> phút</span>
						</p>
					<?php endif; ?>
					<?php if ( $booking_url ) : ?>
						<a class="salon-services__book" href="<?php echo esc_url( add_query_arg( 'service', $service->ID, $booking_url ) ); ?>">Đặt lịch</a>
					<?php endif; ?>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
	return (string) ob_get_clean();
}
