<?php
/**
 * Kiểm tra Phần 5: đăng ký CPT/taxonomy/meta, sanitize settings, lưu meta box, vòng đời plugin.
 * Các thao tác ghi bài viết chạy trong TRANSACTION rồi ROLLBACK → không làm bẩn DB.
 *
 * Chạy (đứng ở C:\xampp\htdocs\wordpress):
 *   C:\xampp\php\php.exe wp-content\plugins\salon-booking\dev\test-plugin.php
 */
if ( PHP_SAPI !== 'cli' ) {
	exit;
}
require dirname( __DIR__, 4 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/template.php'; // add_settings_error(), get_settings_errors()
require_once ABSPATH . 'wp-admin/includes/plugin.php';   // activate_plugin(), deactivate_plugins()
require_once dirname( __DIR__ ) . '/admin/service-meta-box.php'; // CLI không phải is_admin() → tự nạp

$pass = 0;
$fail = 0;
function check( $label, $cond ) {
	global $pass, $fail;
	$cond ? $pass++ : $fail++;
	echo ( $cond ? '  ✔ ' : '  ✘ ' ) . $label . "\n";
}

echo "== 1. Đăng ký ==\n";
check( 'CPT salon_service, rest_base = services', 'services' === get_post_type_object( 'salon_service' )->rest_base );
check( 'Taxonomy salon_service_cat gắn với salon_service', is_object_in_taxonomy( 'salon_service', 'salon_service_cat' ) );
check( 'Taxonomy phân cấp (giống Category)', is_taxonomy_hierarchical( 'salon_service_cat' ) );
$meta_keys = get_registered_meta_keys( 'post', 'salon_service' );
check( 'register_post_meta: _salon_price kiểu integer, show_in_rest', isset( $meta_keys['_salon_price'] ) && 'integer' === $meta_keys['_salon_price']['type'] && $meta_keys['_salon_price']['show_in_rest'] );
check( '_salon_price là protected meta (ẩn khỏi Custom Fields)', is_protected_meta( '_salon_price', 'post' ) );
check( 'Dịch vụ son-gel thuộc loại mong-tay', has_term( 'mong-tay', 'salon_service_cat', 14 ) );
check( 'salon_get_booking_url() trỏ tới trang đã chọn', false !== strpos( salon_get_booking_url( array( 'service' => 14 ) ), 'dat-lich/?service=14' ) );

echo "== 2. Sanitize settings (gọi sanitize_option = đúng đường update_option đi qua) ==\n";
$saved = salon_get_settings();

$out = sanitize_option(
	'salon_settings',
	array(
		'booking_page_id' => '23',
		'open_time'       => '09:00',
		'close_time'      => '21:30',
		'slot_minutes'    => '15',
		'notify_email'    => ' le-tan@salon.test ',
	)
);
check( 'Dữ liệu hợp lệ: chuỗi "15" → int 15', 15 === $out['slot_minutes'] );
check( 'Email được trim', 'le-tan@salon.test' === $out['notify_email'] );
check( 'Checkbox không gửi → false', false === $out['delete_data_on_uninstall'] );
check( 'Không có lỗi', array() === get_settings_errors( 'salon_settings' ) );

$out = sanitize_option(
	'salon_settings',
	array(
		'booking_page_id' => '14',            // ID của DỊCH VỤ, không phải page
		'open_time'       => '20:00',
		'close_time'      => '08:00',         // đóng trước mở
		'slot_minutes'    => '7',             // ngoài whitelist
		'notify_email'    => 'khong-phai-email',
		'delete_data_on_uninstall' => '1',
	)
);
check( 'Page ID sai → giữ giá trị cũ', $saved['booking_page_id'] === $out['booking_page_id'] );
check( 'Giờ đảo ngược → giữ giờ cũ', $saved['open_time'] === $out['open_time'] && $saved['close_time'] === $out['close_time'] );
check( 'slot 7 → giữ giá trị cũ', $saved['slot_minutes'] === $out['slot_minutes'] );
check( 'Email sai → giữ giá trị cũ', $saved['notify_email'] === $out['notify_email'] );
check( 'Checkbox có gửi → true', true === $out['delete_data_on_uninstall'] );
$codes = wp_list_pluck( get_settings_errors( 'salon_settings' ), 'code' );
check( '4 lỗi: ' . implode( ', ', $codes ), 4 === count( $codes ) );
check( 'Option trong DB không đổi (sanitize_option chỉ tính, không lưu)', salon_get_settings() === $saved );

echo "== 3. Lưu meta box (save_post_salon_service) — trong transaction ==\n";
global $wpdb;
$wpdb->query( 'START TRANSACTION' );

$service_id = 14;
$before     = salon_get_service_details( $service_id );
wp_set_current_user( 1 );

/** Giả lập bấm Update với $_POST cho trước, trả về meta sau khi lưu. */
function submit_service( $post_id, array $post_data ) {
	$_POST = $post_data;
	wp_update_post( array( 'ID' => $post_id ) ); // → wp_insert_post → do_action( 'save_post_salon_service' )
	$_POST = array();
	clean_post_cache( $post_id );
	return salon_get_service_details( $post_id );
}

$r = submit_service( $service_id, array( 'salon_price' => '999000', 'salon_duration' => '50' ) );
check( 'Không có nonce (vd REST/Quick Edit) → không lưu', $r === $before );

$r = submit_service( $service_id, array( 'salon_service_nonce' => wp_create_nonce( 'salon_save_service_16' ), 'salon_price' => '999000', 'salon_duration' => '50' ) );
check( 'Nonce của dịch vụ KHÁC (#16) → không lưu', $r === $before );

$nonce = wp_create_nonce( 'salon_save_service_' . $service_id );
$r     = submit_service( $service_id, array( 'salon_service_nonce' => $nonce, 'salon_price' => '199000', 'salon_duration' => '50' ) );
check( 'Admin + nonce đúng → lưu 199000đ / 50 phút', 199000 === $r['price'] && 50 === $r['duration'] );

$r = submit_service( $service_id, array( 'salon_service_nonce' => $nonce, 'salon_price' => '-5', 'salon_duration' => '7' ) );
check( 'Giá "-5" → absint = 5 (lưu); thời lượng 7 (không chia hết 5) → giữ 50', 5 === $r['price'] && 50 === $r['duration'] );

$sub_id = wp_insert_user( array( 'user_login' => 'test_sub_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
wp_set_current_user( $sub_id );
$r = submit_service( $service_id, array( 'salon_service_nonce' => wp_create_nonce( 'salon_save_service_' . $service_id ), 'salon_price' => '1', 'salon_duration' => '5' ) );
check( 'Subscriber có nonce HỢP LỆ của chính mình nhưng thiếu quyền → không lưu', 5 === $r['price'] && 50 === $r['duration'] );
wp_set_current_user( 1 );

// register_post_meta sanitize_callback chạy cả khi code khác gọi update_post_meta trực tiếp.
update_post_meta( $service_id, '_salon_price', '12abc' );
check( 'update_post_meta("12abc") → absint → 12', 12 === (int) get_post_meta( $service_id, '_salon_price', true ) );

$wpdb->query( 'ROLLBACK' );
wp_cache_flush();
check( 'ROLLBACK: meta về như cũ (' . salon_format_price( $before['price'] ) . ')', salon_get_service_details( $service_id ) === $before );

echo "== 4. Vòng đời: deactivate → activate ==\n";
$plugin = plugin_basename( SALON_FILE );
$rules  = static function () {
	return array_keys( (array) get_option( 'rewrite_rules' ) );
};
$has_rule = static function ( $needle ) use ( $rules ) {
	foreach ( $rules() as $regex ) {
		if ( 0 === strpos( $regex, $needle ) ) {
			return true;
		}
	}
	return false;
};

check( 'Trước: có rule dich-vu/ và loai-dich-vu/', $has_rule( 'dich-vu/' ) && $has_rule( 'loai-dich-vu/' ) );

deactivate_plugins( $plugin ); // → do_action( 'deactivate_salon-booking/salon-booking.php' ) → salon_deactivate()
check( 'Sau deactivate: không còn rule dich-vu/', ! $has_rule( 'dich-vu/' ) && ! $has_rule( 'loai-dich-vu/' ) );
check( 'Sau deactivate: dữ liệu còn nguyên (bảng + option)', $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}salon_bookings'" ) && get_option( 'salon_settings' ) );

$result = activate_plugin( $plugin ); // → do_action( 'activate_salon-booking/salon-booking.php' ) → salon_activate()
check( 'activate_plugin() không lỗi', null === $result );
check( 'Sau activate: có lại rule dich-vu/ và loai-dich-vu/', $has_rule( 'dich-vu/' ) && $has_rule( 'loai-dich-vu/' ) );
check( 'Cài đặt của admin KHÔNG bị reset (add_option không ghi đè)', 23 === (int) salon_get_setting( 'booking_page_id' ) );
check( 'Plugin nằm trong active_plugins', is_plugin_active( $plugin ) );

echo "== 5. Uninstall khi admin KHÔNG tick xoá dữ liệu ==\n";
$posts_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('salon_service','salon_staff')" );
define( 'WP_UNINSTALL_PLUGIN', $plugin );
include dirname( __DIR__ ) . '/uninstall.php';
check( "Vẫn còn {$posts_before} dịch vụ/nhân viên", (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('salon_service','salon_staff')" ) === $posts_before );
check( 'Vẫn còn bảng và option', $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}salon_bookings'" ) && get_option( 'salon_db_version' ) );

echo "\nKết quả: {$pass} đạt, {$fail} lỗi\n";
exit( $fail ? 1 : 0 );
