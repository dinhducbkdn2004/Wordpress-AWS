<?php
/**
 * Validate dữ liệu đặt lịch, KHÔNG phụ thuộc giao diện.
 * Form (Phần 7) và REST API (Phần 8) cùng gọi salon_validate_booking_input().
 *
 * Nguyên tắc:
 *  - Nhận dữ liệu THÔ (đã wp_unslash), chỉ đọc các key biết trước (whitelist).
 *  - Dữ liệu có định dạng cố định (ID, ngày, giờ, SĐT, email) → VALIDATE: sai là từ chối, không "sửa hộ".
 *  - Text tự do (tên, ghi chú) → SANITIZE: làm sạch rồi chấp nhận, sau đó kiểm tra độ dài.
 *  - Trả về WP_Error chứa NHIỀU lỗi, mỗi lỗi có code = tên field → form hiện lỗi cạnh từng ô.
 *
 * @package SalonBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Các field form/REST được phép gửi lên.
 *
 * @return string[]
 */
function salon_booking_input_keys() {
	return array( 'service_id', 'staff_id', 'date', 'time', 'customer_name', 'phone', 'email', 'note' );
}

/**
 * Giới hạn nghiệp vụ. Có filter để site khác chỉnh (vd cho đặt trước 60 ngày).
 *
 * @return array<string,int>
 */
function salon_booking_limits() {
	return apply_filters(
		'salon_booking_limits',
		array(
			'name_min'         => 2,
			'name_max'         => 100, // = varchar(100) trong bảng
			'email_max'        => 100,
			'note_max'         => 500,
			'max_days_ahead'   => 30,
			'min_lead_minutes' => 30,  // đặt cho hôm nay phải trước ít nhất 30 phút
		)
	);
}

/**
 * 'HH:MM' → số phút từ 00:00.
 *
 * @param string $hhmm Giờ dạng HH:MM (đã được validate ở Settings).
 * @return int
 */
function salon_time_to_minutes( $hhmm ) {
	$parts = array_map( 'intval', explode( ':', (string) $hhmm ) );
	return $parts[0] * 60 + ( $parts[1] ?? 0 );
}

/**
 * Danh sách giờ bắt đầu hợp lệ theo Settings: 08:00, 08:30, ... (trước giờ đóng cửa).
 *
 * @return string[]
 */
function salon_get_time_slots() {
	$step  = (int) salon_get_setting( 'slot_minutes' );
	$start = salon_time_to_minutes( salon_get_setting( 'open_time' ) );
	$end   = salon_time_to_minutes( salon_get_setting( 'close_time' ) );

	$slots = array();
	for ( $t = $start; $step > 0 && $t < $end; $t += $step ) {
		$slots[] = sprintf( '%02d:%02d', intdiv( $t, 60 ), $t % 60 );
	}
	return $slots;
}

/**
 * Chuẩn hoá SĐT di động Việt Nam: "+84 912.345.678" → "0912345678".
 *
 * @param string $raw Chuỗi người dùng nhập.
 * @return string Số đã chuẩn hoá, hoặc '' nếu không hợp lệ.
 */
function salon_normalize_phone( $raw ) {
	$phone = preg_replace( '/[\s.\-()]/', '', (string) $raw ); // chỉ bỏ ký tự phân cách hay gặp
	if ( 0 === strpos( $phone, '+84' ) ) {
		$phone = '0' . substr( $phone, 3 );
	} elseif ( preg_match( '/^84\d{9}$/', $phone ) ) {
		$phone = '0' . substr( $phone, 2 );
	}
	// Đầu số di động VN: 03x, 05x, 07x, 08x, 09x + 8 số. Còn ký tự lạ (chữ, <, ') → không khớp.
	return preg_match( '/^0[35789]\d{8}$/', $phone ) ? $phone : '';
}

/**
 * Lấy post đã publish đúng post type (chặn ID của post nháp, post type khác, post đã xoá).
 *
 * @param int    $id   Post ID.
 * @param string $type Post type.
 * @return WP_Post|null
 */
function salon_get_published( $id, $type ) {
	$post = $id > 0 ? get_post( $id ) : null;
	return ( $post && $type === $post->post_type && 'publish' === $post->post_status ) ? $post : null;
}

/**
 * Tìm nhân viên đầu tiên còn rảnh (khi khách chọn "Ai rảnh cũng được").
 *
 * @param string $start_utc Bắt đầu (UTC).
 * @param string $end_utc   Kết thúc (UTC).
 * @return int Staff ID, 0 nếu không ai rảnh.
 */
function salon_find_free_staff( $start_utc, $end_utc ) {
	$ids = get_posts(
		array(
			'post_type'   => 'salon_staff',
			'post_status' => 'publish',
			'fields'      => 'ids',
			'numberposts' => -1,
			'orderby'     => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
		)
	);

	/**
	 * Lọc danh sách nhân viên được xét (vd chỉ ai làm được dịch vụ này — bài tập Phần 5).
	 */
	$ids = apply_filters( 'salon_booking_candidate_staff', $ids, $start_utc, $end_utc );

	foreach ( $ids as $id ) {
		if ( salon_is_slot_available( $id, $start_utc, $end_utc ) ) {
			return (int) $id;
		}
	}
	return 0;
}

/**
 * Validate + sanitize dữ liệu đặt lịch.
 *
 * @param array $input Dữ liệu thô ĐÃ wp_unslash, key theo salon_booking_input_keys().
 * @return array|WP_Error Mảng sẵn sàng cho salon_create_booking(), hoặc WP_Error (code = tên field).
 */
function salon_validate_booking_input( array $input ) {
	$errors = new WP_Error();
	$limits = salon_booking_limits();

	// 0. Whitelist key + chỉ nhận giá trị vô hướng (chặn salon_booking[phone][]=x làm PHP warning/TypeError).
	$in = array();
	foreach ( salon_booking_input_keys() as $key ) {
		$in[ $key ] = ( isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ) ? trim( (string) $input[ $key ] ) : '';
	}

	// 1. Dịch vụ: phải là ID của 1 salon_service đã publish. ctype_digit chặn "14abc" (absint sẽ ra 14!).
	$service = ctype_digit( $in['service_id'] ) ? salon_get_published( (int) $in['service_id'], 'salon_service' ) : null;
	if ( ! $service ) {
		$errors->add( 'service_id', 'Vui lòng chọn dịch vụ.' );
	}

	// 2. Nhân viên: rỗng hoặc 0 = "Ai rảnh cũng được".
	$staff = null;
	if ( '' !== $in['staff_id'] && '0' !== $in['staff_id'] ) {
		$staff = ctype_digit( $in['staff_id'] ) ? salon_get_published( (int) $in['staff_id'], 'salon_staff' ) : null;
		if ( ! $staff ) {
			$errors->add( 'staff_id', 'Nhân viên không hợp lệ.' );
		}
	}

	// 3. Ngày: đúng định dạng Y-m-d, có thật (không phải 31/02), không ở quá khứ, không quá xa.
	$tz    = wp_timezone();           // Asia/Ho_Chi_Minh — KHÔNG dùng date_default_timezone (WP luôn để UTC)
	$now   = current_datetime();      // "bây giờ" theo giờ site
	$today = $now->format( 'Y-m-d' );
	$last  = $now->modify( '+' . (int) $limits['max_days_ahead'] . ' days' )->format( 'Y-m-d' );
	$date  = DateTimeImmutable::createFromFormat( '!Y-m-d', $in['date'], $tz );

	if ( ! $date || $date->format( 'Y-m-d' ) !== $in['date'] ) {
		$errors->add( 'date', 'Vui lòng chọn ngày hợp lệ.' );
		$date = null;
	} elseif ( $in['date'] < $today ) { // so sánh chuỗi Y-m-d đúng thứ tự thời gian
		$errors->add( 'date', 'Không thể đặt lịch cho ngày đã qua.' );
		$date = null;
	} elseif ( $in['date'] > $last ) {
		$errors->add( 'date', sprintf( 'Chỉ nhận đặt lịch trong vòng %d ngày tới.', $limits['max_days_ahead'] ) );
		$date = null;
	}

	// 4. Giờ: phải nằm trong danh sách slot (whitelist) → không cần parse "9h", "25:99"...
	$start = null;
	$end   = null;
	if ( ! in_array( $in['time'], salon_get_time_slots(), true ) ) {
		$errors->add( 'time', 'Vui lòng chọn giờ trong khung giờ mở cửa.' );
	} elseif ( $date && $service ) {
		$duration = salon_get_service_details( $service->ID )['duration'];
		$duration = $duration > 0 ? $duration : (int) salon_get_setting( 'slot_minutes' );
		$start    = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $in['date'] . ' ' . $in['time'], $tz );
		$end      = $start->modify( "+{$duration} minutes" );
		$close    = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $in['date'] . ' ' . salon_get_setting( 'close_time' ), $tz );

		if ( $start < $now->modify( '+' . (int) $limits['min_lead_minutes'] . ' minutes' ) ) {
			$errors->add( 'time', sprintf( 'Giờ này đã qua hoặc quá gần, vui lòng đặt trước ít nhất %d phút.', $limits['min_lead_minutes'] ) );
		} elseif ( $end > $close ) {
			$errors->add(
				'time',
				sprintf( '"%1$s" mất %2$d phút, salon đóng cửa lúc %3$s. Vui lòng chọn giờ sớm hơn.', $service->post_title, $duration, salon_get_setting( 'close_time' ) )
			);
		}
	}

	// 5. Họ tên: text tự do → sanitize (bỏ thẻ HTML, xuống dòng, khoảng trắng thừa) rồi đo độ dài.
	$name = sanitize_text_field( $in['customer_name'] );
	if ( mb_strlen( $name ) < $limits['name_min'] ) {
		$errors->add( 'customer_name', sprintf( 'Vui lòng nhập họ tên (ít nhất %d ký tự).', $limits['name_min'] ) );
	} elseif ( mb_strlen( $name ) > $limits['name_max'] ) {
		$errors->add( 'customer_name', sprintf( 'Họ tên tối đa %d ký tự.', $limits['name_max'] ) );
	}

	// 6. SĐT: bắt buộc, chuẩn hoá rồi validate.
	$phone = salon_normalize_phone( $in['phone'] );
	if ( '' === $in['phone'] ) {
		$errors->add( 'phone', 'Vui lòng nhập số điện thoại.' );
	} elseif ( '' === $phone ) {
		$errors->add( 'phone', 'Số điện thoại không hợp lệ (vd: 0912 345 678).' );
	}

	// 7. Email: không bắt buộc. Validate chuỗi GỐC (bài học Phần 5: sanitize_email('abc') = '' → tưởng là "để trống").
	$email = '';
	if ( '' !== $in['email'] ) {
		if ( strlen( $in['email'] ) > $limits['email_max'] || ! is_email( $in['email'] ) ) {
			$errors->add( 'email', 'Email không hợp lệ.' );
		} else {
			$email = sanitize_email( $in['email'] );
		}
	}

	// 8. Ghi chú: sanitize_textarea_field GIỮ xuống dòng (khác sanitize_text_field).
	$note = sanitize_textarea_field( $in['note'] );
	if ( mb_strlen( $note ) > $limits['note_max'] ) {
		$errors->add( 'note', sprintf( 'Ghi chú tối đa %d ký tự.', $limits['note_max'] ) );
	}

	// Có lỗi định dạng → dừng, không tốn query kiểm tra lịch trống.
	if ( $errors->has_errors() ) {
		return $errors;
	}

	// 9. Nghiệp vụ: còn chỗ không? (lưu UTC — quy ước từ Phần 2)
	$utc       = new DateTimeZone( 'UTC' );
	$start_utc = $start->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
	$end_utc   = $end->setTimezone( $utc )->format( 'Y-m-d H:i:s' );

	if ( $staff ) {
		$staff_id = salon_is_slot_available( $staff->ID, $start_utc, $end_utc ) ? $staff->ID : 0;
		if ( ! $staff_id ) {
			$errors->add( 'time', sprintf( '%1$s đã có lịch lúc %2$s. Vui lòng chọn giờ khác hoặc chọn "Ai rảnh cũng được".', $staff->post_title, $in['time'] ) );
		}
	} else {
		$staff_id = salon_find_free_staff( $start_utc, $end_utc );
		if ( ! $staff_id ) {
			$errors->add( 'time', 'Khung giờ này đã kín lịch, vui lòng chọn giờ khác.' );
		}
	}

	if ( $errors->has_errors() ) {
		return $errors;
	}

	return array(
		'service_id'    => $service->ID,
		'staff_id'      => (int) $staff_id,
		'start_at'      => $start_utc,
		'end_at'        => $end_utc,
		'customer_name' => $name,
		'phone'         => $phone,
		'email'         => $email,
		'note'          => $note,
		'status'        => 'pending', // khách KHÔNG được tự chọn trạng thái — luôn đặt ở server
	);
}
