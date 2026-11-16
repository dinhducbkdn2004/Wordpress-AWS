<?php
/**
 * Vòng đời plugin: activate → (upgrade) → deactivate. Gỡ cài đặt nằm ở uninstall.php.
 *
 *   Bấm Activate   → salon_activate()   : tạo bảng, option mặc định, flush rewrite
 *   Ghi đè file mới → salon_maybe_upgrade(): activation KHÔNG chạy lại → tự so version
 *   Bấm Deactivate → salon_deactivate() : chỉ dọn thứ "tạm" (rewrite, cron). KHÔNG xoá dữ liệu
 *   Bấm Delete     → uninstall.php      : xoá dữ liệu (nếu admin cho phép)
 *
 * @package SalonBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Chạy 1 lần khi bấm Activate.
 */
function salon_activate() {
	salon_install();

	// Lúc activate, request này đã qua 'init' TRƯỚC khi plugin được bật → CPT/taxonomy
	// của mình CHƯA được đăng ký. Phải tự đăng ký rồi mới flush, nếu không rewrite rules
	// sinh ra sẽ thiếu /dich-vu/ → trang dịch vụ báo 404.
	salon_register_post_types();
	salon_register_taxonomies();
	flush_rewrite_rules( false ); // false = không ghi lại .htaccess (CPT không cần)
}

/**
 * Chạy khi bấm Deactivate. Chỉ dọn rewrite rules, giữ nguyên dữ liệu
 * (admin có thể bật lại ngay — vd tắt tạm để tìm plugin gây lỗi).
 */
function salon_deactivate() {
	// Trong request deactivate, plugin VẪN đang được nạp → CPT vẫn đăng ký.
	// Nếu flush ngay thì rule /dich-vu/ vẫn còn. Phải gỡ đăng ký trước rồi mới flush.
	unregister_taxonomy( 'salon_service_cat' );
	unregister_post_type( 'salon_service' );
	unregister_post_type( 'salon_staff' );
	flush_rewrite_rules( false );

	// Sau này có WP-Cron (Phần 10): wp_clear_scheduled_hook( 'salon_daily_reminder' );
}

/**
 * Activation hook KHÔNG chạy khi cập nhật plugin (upload/ghi đè file mới) → mỗi request
 * so version lưu trong DB với hằng trong code. Option này autoload nên không tốn query.
 */
function salon_maybe_upgrade() {
	if ( get_option( 'salon_db_version' ) === SALON_DB_VERSION ) {
		return;
	}

	salon_install();

	// Đang ở plugins_loaded: CPT chưa đăng ký (init chưa chạy) → KHÔNG flush ở đây.
	// Xoá option là đủ: WP_Rewrite thấy rỗng sẽ tự tạo lại, và hoãn việc lưu tới
	// wp_loaded (sau init) — xem class-wp-rewrite.php refresh_rewrite_rules().
	delete_option( 'rewrite_rules' );
}

/**
 * Tạo/cập nhật bảng + option mặc định. Gọi nhiều lần vẫn an toàn (idempotent):
 * dbDelta chỉ ALTER phần khác, add_option không ghi đè option đã có.
 */
function salon_install() {
	global $wpdb;
	$table   = $wpdb->prefix . 'salon_bookings';   // không hardcode "wp_": prefix có thể khác
	$charset = $wpdb->get_charset_collate();       // utf8mb4 → lưu được tiếng Việt và emoji

	$sql = "CREATE TABLE $table (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  customer_name varchar(100) NOT NULL,
  phone varchar(20) NOT NULL,
  email varchar(100) NOT NULL DEFAULT '',
  service_id bigint(20) unsigned NOT NULL,
  staff_id bigint(20) unsigned NOT NULL,
  start_at datetime NOT NULL,
  end_at datetime NOT NULL,
  note text NULL,
  status varchar(20) NOT NULL DEFAULT 'pending',
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY staff_start (staff_id,start_at),
  KEY status (status)
) $charset;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php'; // dbDelta() không được nạp sẵn
	dbDelta( $sql );

	// 1 option dạng mảng cho mọi cài đặt (1 dòng wp_options thay vì 6 dòng).
	// add_option: nếu đã có thì KHÔNG đụng vào → cài lại không làm mất cấu hình của admin.
	add_option( 'salon_settings', salon_settings_defaults() ); // autoload mặc định: đọc ở frontend

	update_option( 'salon_db_version', SALON_DB_VERSION, true );
}
