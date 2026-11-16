<?php
/**
 * Render phía server của block salon/featured-services (khai báo ở block.json: "render").
 *
 * Core bọc file này trong một closure rồi `require` ở MỖI lần block xuất hiện (blocks.php,
 * register_block_type_from_metadata) → có sẵn 3 biến:
 *   $attributes  attribute đã được kiểm tra kiểu theo block.json (sai kiểu → dùng default)
 *   $content     HTML bên trong (block này không có)
 *   $block       đối tượng WP_Block
 *
 * KHÔNG khai báo function trong file này: require nhiều lần → "Cannot redeclare" fatal.
 *
 * @package SalonBooking
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

defined( 'ABSPATH' ) || exit;

$salon_html = salon_render_services(
	array(
		'limit'      => $attributes['count'],
		'columns'    => $attributes['columns'],
		'category'   => $attributes['category'],
		'show_price' => $attributes['showPrice'],
		// Đặt block này ở sidebar trang chi tiết dịch vụ → không gợi ý lại chính dịch vụ đang xem.
		'exclude'    => is_singular( 'salon_service' ) ? get_queried_object_id() : 0,
	)
);

// get_block_wrapper_attributes(): class "wp-block-salon-featured-services" + class/style
// sinh từ "supports" (align, màu, spacing) mà người dùng chọn trong editor.
printf(
	'<div %1$s>%2$s</div>',
	get_block_wrapper_attributes(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core đã escape
	$salon_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- đã escape trong salon_render_services()
);
