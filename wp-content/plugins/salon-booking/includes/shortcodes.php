<?php
/**
 * Shortcode [salon_services].
 *
 * Ví dụ: [salon_services limit="4" category="mong-tay" columns="2" show_price="no"]
 *
 * Shortcode được xử lý ở filter the_content, priority 11 (do_shortcode), SAU do_blocks (9) và wpautop (10).
 *
 * @package SalonBooking
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'salon_register_shortcodes' );

function salon_register_shortcodes() {
	add_shortcode( 'salon_services', 'salon_shortcode_services' ); // tag có prefix → không đụng plugin khác
}

/**
 * @param array|string $atts    Thuộc tính người dùng gõ (CHUỖI, chưa tin được). '' nếu không có thuộc tính nào.
 * @param string|null  $content Nội dung giữa [tag]...[/tag] (shortcode này không dùng).
 * @param string       $tag     Tên shortcode.
 * @return string HTML — PHẢI return, không echo (echo sẽ in ra TRƯỚC nội dung bài viết).
 */
function salon_shortcode_services( $atts, $content = null, $tag = '' ) {
	// shortcode_atts: chỉ giữ các key khai báo ở đây (key lạ bị bỏ), thiếu key nào thì lấy mặc định.
	// Tham số thứ 3 = tên shortcode → bật filter "shortcode_atts_salon_services" cho người khác chỉnh mặc định.
	$atts = shortcode_atts(
		array(
			'limit'      => 6,
			'category'   => '',
			'columns'    => 3,
			'show_price' => 'yes',
		),
		$atts,
		$tag
	);

	wp_enqueue_style( 'salon-frontend' ); // WP 6.9+ classic theme: style enqueue muộn vẫn được đưa lên <head>

	return salon_render_services(
		array(
			'limit'      => $atts['limit'],
			'category'   => $atts['category'],
			'columns'    => $atts['columns'],
			// Mọi thuộc tính shortcode là CHUỖI: (bool) "no" === true! → phải tự hiểu yes/no/true/false/1/0.
			'show_price' => filter_var( $atts['show_price'], FILTER_VALIDATE_BOOLEAN ),
		)
	);
}
