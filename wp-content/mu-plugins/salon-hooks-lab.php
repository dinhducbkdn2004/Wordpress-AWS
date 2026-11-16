<?php
/**
 * Plugin Name: Salon Hooks Lab (học tập)
 * Description: Thực hành action/filter — mở rộng plugin salon-booking mà KHÔNG sửa code của nó.
 */
defined( 'ABSPATH' ) || exit;

// File này nạp TRƯỚC plugin salon-booking (mu-plugin luôn nạp trước) mà vẫn hook được
// vào 'salon_booking_data' — vì add_filter() chỉ ghi tên vào sổ đăng ký, hook không cần "khai báo" trước.

// 1. FILTER, priority 5 (chạy sớm): chuẩn hoá SĐT "+84 912-345-678" → "0912345678".
add_filter( 'salon_booking_data', 'salon_lab_normalize_phone', 5 );
function salon_lab_normalize_phone( $data ) {
	$phone = preg_replace( '/\D+/', '', (string) $data['phone'] ); // bỏ mọi ký tự không phải số
	if ( preg_match( '/^84(\d{9})$/', $phone, $m ) ) {
		$phone = '0' . $m[1];
	}
	$data['phone'] = $phone;
	return $data; // FILTER bắt buộc return — quên dòng này thì $data thành null
}

// 2. FILTER, priority 20 (chạy sau bước 1): chuẩn hoá tên "  nguyễn văn a " → "Nguyễn Văn A".
add_filter(
	'salon_booking_data',
	function ( $data ) {
		$data['customer_name'] = mb_convert_case( trim( $data['customer_name'] ), MB_CASE_TITLE, 'UTF-8' );
		return $data;
	},
	20
);

// 3. FILTER: thêm trạng thái mới vào danh sách của plugin.
add_filter(
	'salon_booking_statuses',
	function ( $statuses ) {
		$statuses['no_show'] = 'Khách không đến';
		return $statuses;
	}
);

// 4. ACTION nhận 2 tham số → phải khai báo accepted_args = 2, nếu không chỉ nhận được $booking_id.
add_action( 'salon_booking_created', 'salon_lab_notify', 10, 2 );
function salon_lab_notify( $booking_id, $data ) {
	error_log( sprintf( '[salon] Lịch mới #%d: %s (%s) lúc %s UTC', $booking_id, $data['customer_name'], $data['phone'], $data['start_at'] ) );
	// Thực tế: wp_mail( get_option( 'admin_email' ), 'Lịch hẹn mới', ... ) hoặc gửi SMS/Zalo.
}

// 5. FILTER của Core: thêm nút "Đặt lịch" cuối nội dung bài viết.
add_filter( 'the_content', 'salon_lab_cta', 20 ); // 20: sau do_blocks (9), wpautop (10), do_shortcode (11)
function salon_lab_cta( $content ) {
	// the_content còn chạy ở excerpt, widget, REST, feed... → chỉ sửa đúng bài chính đang xem.
	if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	return $content . '<p class="salon-cta"><a href="' . esc_url( home_url( '/dat-lich/' ) ) . '">Đặt lịch ngay</a></p>';
}
