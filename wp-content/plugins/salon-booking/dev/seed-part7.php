<?php
/**
 * Dữ liệu Phần 7: đặt shortcode [salon_booking_form] vào trang Đặt lịch (Settings → Salon Booking).
 * Chạy lại nhiều lần không sao.
 *
 * Chạy (đứng ở C:\xampp\htdocs\wordpress):
 *   C:\xampp\php\php.exe wp-content\plugins\salon-booking\dev\seed-part7.php
 */
if ( PHP_SAPI !== 'cli' ) {
	exit;
}
require dirname( __DIR__, 4 ) . '/wp-load.php'; // plugins_loaded → salon_maybe_upgrade(): DB 1.1 → 1.2 (thêm cột email)

global $wpdb;
echo 'salon_db_version = ' . get_option( 'salon_db_version' ) . "\n";
$has_email = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM ' . salon_bookings_table() . ' LIKE %s', 'email' ) );
echo $has_email ? "  ✔ Bảng có cột email (dbDelta đã ALTER TABLE)\n" : "  ✘ Thiếu cột email\n";

$page_id = (int) salon_get_setting( 'booking_page_id' );
if ( ! $page_id || 'page' !== get_post_type( $page_id ) ) {
	exit( "  ✘ Chưa chọn Trang đặt lịch trong Settings → Salon Booking\n" );
}

$content = "<!-- wp:paragraph -->\n<p>Chọn dịch vụ và giờ phù hợp, salon sẽ gọi điện xác nhận trong vòng 30 phút (giờ làm việc).</p>\n<!-- /wp:paragraph -->\n\n"
	. "<!-- wp:shortcode -->\n[salon_booking_form]\n<!-- /wp:shortcode -->";

$result = wp_update_post(
	array(
		'ID'           => $page_id,
		'post_content' => $content,
	),
	true
);
echo is_wp_error( $result ) ? '  ✘ ' . $result->get_error_message() . "\n" : "  ✔ Trang #{$page_id} " . get_permalink( $page_id ) . " đã có [salon_booking_form]\n";
