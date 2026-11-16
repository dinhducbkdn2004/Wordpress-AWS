<?php
if ( PHP_SAPI !== 'cli' ) exit;                          // chỉ cho chạy từ terminal
require dirname( __DIR__, 4 ) . '/wp-load.php';         // bootstrap WP như Phần 1, nhưng không có template
global $wpdb;
$table = $wpdb->prefix . 'salon_bookings';

// CREATE: dịch vụ = 1 dòng wp_posts + các dòng wp_postmeta
$service_id = wp_insert_post( array(
	'post_type'   => 'salon_service',
	'post_status' => 'publish',
	'post_title'  => 'Sơn gel',
	'meta_input'  => array( '_salon_price' => 150000, '_salon_duration' => 45 ),
), true );                                               // true: trả WP_Error thay vì 0 khi lỗi
$staff_id = wp_insert_post( array( 'post_type' => 'salon_staff', 'post_status' => 'publish', 'post_title' => 'Chị Lan' ), true );

// CREATE: lịch hẹn = bảng riêng; mảng format khớp thứ tự từng cột
$wpdb->insert( $table, array(
	'customer_name' => 'Nguyễn Văn A', 'phone' => '0912345678',
	'service_id' => $service_id, 'staff_id' => $staff_id,
	'start_at' => '2026-11-15 02:00:00', 'end_at' => '2026-11-15 02:45:00', // = 9:00–9:45 giờ VN
	'status' => 'pending', 'created_at' => current_time( 'mysql', true ),    // true = giờ UTC
), array( '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' ) );
$booking_id = $wpdb->insert_id;

// READ: JOIN bảng riêng với wp_posts để lấy tên dịch vụ
$rows = $wpdb->get_results( $wpdb->prepare(
	"SELECT b.id, b.customer_name, p.post_title AS service
	 FROM {$table} b JOIN {$wpdb->posts} p ON p.ID = b.service_id
	 WHERE b.status = %s", 'pending' ) );
print_r( $rows );

// UPDATE
$wpdb->update( $table, array( 'status' => 'confirmed' ), array( 'id' => $booking_id ), array( '%s' ), array( '%d' ) );

// WP_Query sinh ra SQL gì?
$q = new WP_Query( array( 'post_type' => 'salon_service', 'meta_query' => array(
	array( 'key' => '_salon_price', 'value' => 200000, 'compare' => '<=', 'type' => 'NUMERIC' ) ) ) );
echo $q->request, "\n";
echo 'Permalink: ', get_permalink( $service_id ), "\n";
echo 'Tổng số query: ', $wpdb->num_queries, "\n";
