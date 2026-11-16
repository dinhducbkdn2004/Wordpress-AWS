<?php
/**
 * Chạy khi admin bấm "Delete" plugin (sau khi đã Deactivate).
 *
 * WP include file này từ uninstall_plugin() (wp-admin/includes/plugin.php) — plugin KHÔNG được nạp
 * (đã deactivate) → không có hàm/CPT/taxonomy của plugin, trừ khi tự require.
 *
 * Mặc định GIỮ dữ liệu. Chỉ xoá khi admin đã tick "Xoá toàn bộ dữ liệu" trong Settings → Salon Booking:
 * mất lịch hẹn của khách là không lấy lại được.
 *
 * @package SalonBooking
 */

// Chặn mở file trực tiếp qua URL: chỉ uninstall_plugin() mới define hằng này.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$salon_settings = get_option( 'salon_settings', array() );
if ( empty( $salon_settings['delete_data_on_uninstall'] ) ) {
	return; // admin không cho xoá → giữ nguyên mọi thứ
}

global $wpdb;

// 1. Bài viết của CPT (kèm meta, liên kết term, comment): dùng wp_delete_post để WP dọn hết bảng phụ.
//    Không DELETE FROM wp_posts trực tiếp: sẽ bỏ sót wp_postmeta, wp_term_relationships, cache.
$salon_post_ids = $wpdb->get_col(
	"SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('salon_service', 'salon_staff')"
);
foreach ( $salon_post_ids as $salon_post_id ) {
	wp_delete_post( (int) $salon_post_id, true ); // true = xoá hẳn, không vào thùng rác
}

// 2. Term của taxonomy: taxonomy chưa đăng ký (plugin không nạp) → đăng ký tạm để dùng API.
require_once __DIR__ . '/includes/post-types.php';
salon_register_taxonomies();
$salon_term_ids = get_terms(
	array(
		'taxonomy'   => 'salon_service_cat',
		'hide_empty' => false,
		'fields'     => 'ids',
	)
);
if ( ! is_wp_error( $salon_term_ids ) ) {
	foreach ( $salon_term_ids as $salon_term_id ) {
		wp_delete_term( (int) $salon_term_id, 'salon_service_cat' );
	}
}

// 3. Bảng riêng. Tên bảng không truyền qua prepare được (là identifier) → ghép từ prefix tin cậy.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}salon_bookings" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange

// 4. Option.
delete_option( 'salon_settings' );
delete_option( 'salon_db_version' );

// Ghi chú: file này được include BÊN TRONG hàm uninstall_plugin() nên biến ở đây là biến cục bộ
// của hàm đó. Vẫn đặt prefix salon_ theo chuẩn WPCS (PHPCS không biết file được include ở đâu).
