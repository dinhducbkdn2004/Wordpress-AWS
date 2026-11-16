<?php
/**
 * Cài đặt plugin: lưu trong 1 option dạng mảng `salon_settings`.
 *
 * File này nạp ở MỌI nơi (không chỉ admin) vì:
 *  - frontend cần đọc cài đặt (URL trang đặt lịch, giờ mở cửa...)
 *  - register_setting() gắn sanitize vào update_option() → phải có mặt ở mọi nơi có thể ghi option.
 * Phần giao diện (trang Settings → Salon Booking) nằm ở admin/settings-page.php.
 *
 * @package SalonBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Giá trị mặc định. Một nguồn duy nhất, dùng cho cả cài đặt lần đầu và lúc đọc.
 *
 * @return array
 */
function salon_settings_defaults() {
	return array(
		'booking_page_id'          => 0,       // trang chứa form đặt lịch (Phần 7)
		'open_time'                => '08:00', // giờ địa phương của site
		'close_time'               => '20:00',
		'slot_minutes'             => 30,      // bước chọn giờ trên form
		'notify_email'             => '',      // rỗng = dùng email quản trị (Settings → General)
		'delete_data_on_uninstall' => false,   // mặc định KHÔNG xoá dữ liệu khi gỡ plugin
	);
}

/**
 * Đọc toàn bộ cài đặt, luôn đủ key (thiếu key nào thì lấy mặc định).
 * get_option đã có cache trong request → gọi nhiều lần không tốn query.
 *
 * @return array
 */
function salon_get_settings() {
	return wp_parse_args( (array) get_option( 'salon_settings', array() ), salon_settings_defaults() );
}

/**
 * @param string $key Tên cài đặt.
 * @return mixed|null
 */
function salon_get_setting( $key ) {
	$settings = salon_get_settings();
	return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
}

/**
 * URL trang đặt lịch do admin chọn. API cho theme (thay cho hardcode /dat-lich/).
 *
 * @param array $args Tham số query, vd array( 'service' => 14 ).
 * @return string URL, hoặc '' nếu admin chưa chọn trang / trang không còn publish.
 */
function salon_get_booking_url( array $args = array() ) {
	$page_id = (int) salon_get_setting( 'booking_page_id' );
	if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
		return '';
	}
	return add_query_arg( array_map( 'absint', array_filter( $args ) ), get_permalink( $page_id ) );
}

add_action( 'init', 'salon_register_settings' );

/**
 * Đăng ký option với Settings API. Core cũng đăng ký option của nó ở init
 * (register_initial_settings) để dùng chung cho options.php và REST /wp/v2/settings.
 */
function salon_register_settings() {
	register_setting(
		'salon_settings_group', // option group: phải khớp settings_fields() trong form
		'salon_settings',       // option name: tên dòng trong wp_options
		array(
			'type'              => 'object',
			'default'           => salon_settings_defaults(),
			'sanitize_callback' => 'salon_sanitize_settings', // = add_filter( 'sanitize_option_salon_settings' )
			'show_in_rest'      => false,
		)
	);
}

/**
 * Sanitize + validate dữ liệu form TRƯỚC khi lưu.
 *
 * Nguyên tắc: giá trị sai → báo lỗi và GIỮ giá trị cũ của đúng field đó,
 * không lưu bừa và không làm mất các field hợp lệ khác.
 *
 * @param mixed $input Dữ liệu thô ($_POST['salon_settings'], đã được options.php wp_unslash).
 * @return array
 */
function salon_sanitize_settings( $input ) {
	$output = salon_get_settings(); // bắt đầu từ giá trị đang lưu
	$input  = is_array( $input ) ? $input : array();

	// Trang đặt lịch: 0 (chưa chọn) hoặc ID của một PAGE thật.
	$page_id = isset( $input['booking_page_id'] ) ? absint( $input['booking_page_id'] ) : 0;
	if ( 0 === $page_id || 'page' === get_post_type( $page_id ) ) {
		$output['booking_page_id'] = $page_id;
	} else {
		salon_settings_error( 'booking_page_id', 'Trang đặt lịch không hợp lệ.' );
	}

	// Giờ mở/đóng cửa: HH:MM 24h, và mở < đóng.
	$open  = isset( $input['open_time'] ) ? sanitize_text_field( $input['open_time'] ) : '';
	$close = isset( $input['close_time'] ) ? sanitize_text_field( $input['close_time'] ) : '';
	if ( ! salon_is_valid_time( $open ) || ! salon_is_valid_time( $close ) ) {
		salon_settings_error( 'hours_format', 'Giờ mở/đóng cửa phải có dạng HH:MM (vd 08:00).' );
	} elseif ( $open >= $close ) { // so sánh chuỗi "08:00" < "20:00" đúng vì cùng định dạng 2 chữ số
		salon_settings_error( 'hours_order', 'Giờ mở cửa phải trước giờ đóng cửa.' );
	} else {
		$output['open_time']  = $open;
		$output['close_time'] = $close;
	}

	// Bước giờ: chỉ nhận các giá trị trong whitelist.
	$slot = isset( $input['slot_minutes'] ) ? absint( $input['slot_minutes'] ) : 0;
	if ( in_array( $slot, salon_allowed_slot_minutes(), true ) ) {
		$output['slot_minutes'] = $slot;
	} else {
		salon_settings_error( 'slot_minutes', 'Bước giờ không hợp lệ.' );
	}

	// Email: rỗng (dùng email quản trị) hoặc email hợp lệ.
	// BẪY: sanitize_email( 'abc' ) trả về '' → nếu kiểm tra '' sau khi sanitize, email sai sẽ lặng lẽ
	// thành "để trống" mà không báo lỗi. Phải xét "người dùng có để trống không" trên dữ liệu GỐC.
	$raw_email = isset( $input['notify_email'] ) ? trim( (string) $input['notify_email'] ) : '';
	$email     = sanitize_email( $raw_email );
	if ( '' === $raw_email || is_email( $email ) ) {
		$output['notify_email'] = $email;
	} else {
		salon_settings_error( 'notify_email', 'Email nhận thông báo không hợp lệ.' );
	}

	// Checkbox: không tick thì trình duyệt KHÔNG gửi key → phải hiểu "không có" = false.
	$output['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] );

	return $output;
}

/**
 * @return int[]
 */
function salon_allowed_slot_minutes() {
	return array( 15, 30, 60 );
}

/**
 * @param string $time Chuỗi giờ.
 * @return bool
 */
function salon_is_valid_time( $time ) {
	return (bool) preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', (string) $time );
}

/**
 * add_settings_error() nằm trong wp-admin/includes/template.php → không có ở frontend/CLI.
 * Bọc lại để sanitize chạy được ở mọi nơi mà không fatal.
 *
 * @param string $code    Mã lỗi (dùng làm id HTML).
 * @param string $message Nội dung.
 */
function salon_settings_error( $code, $message ) {
	if ( function_exists( 'add_settings_error' ) ) {
		add_settings_error( 'salon_settings', 'salon_' . $code, $message, 'error' );
	}
}
