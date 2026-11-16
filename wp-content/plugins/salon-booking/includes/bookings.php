<?php
/**
 * Truy vấn bảng wp_salon_bookings bằng $wpdb.
 *
 * Quy ước thời gian: cột start_at/end_at lưu UTC (Y-m-d H:i:s).
 * Đầu vào date_from/date_to là ngày theo giờ của site (Settings → General → Timezone).
 *
 * @package SalonBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tên bảng lịch hẹn (luôn đi qua $wpdb->prefix).
 *
 * @return string
 */
function salon_bookings_table() {
	global $wpdb;
	return $wpdb->prefix . 'salon_bookings';
}

/**
 * Đổi ngày địa phương 'Y-m-d' thành thời điểm 00:00 của ngày đó theo UTC.
 *
 * Ví dụ (Asia/Ho_Chi_Minh, UTC+7): '2026-12-01' → '2026-11-30 17:00:00'.
 *
 * @param string $ymd      Ngày dạng Y-m-d.
 * @param int    $add_days Cộng thêm số ngày (dùng để lấy mốc "đầu ngày hôm sau").
 * @return string|null Chuỗi UTC, hoặc null nếu ngày không hợp lệ.
 */
function salon_local_day_start_utc( $ymd, $add_days = 0 ) {
	$ymd  = (string) $ymd;
	$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $ymd, wp_timezone() );

	// createFromFormat chấp nhận '2026-02-31' (tự tràn sang tháng 3) → so lại để loại.
	if ( ! $date || $date->format( 'Y-m-d' ) !== $ymd ) {
		return null;
	}
	if ( $add_days ) {
		$date = $date->modify( '+' . (int) $add_days . ' day' );
	}
	return $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
}

/**
 * Lấy danh sách lịch hẹn có lọc, tìm kiếm, sắp xếp và phân trang.
 *
 * @param array $args {
 *     @type string $status    Trạng thái (pending|confirmed|completed|cancelled). Rỗng = tất cả.
 *     @type int    $staff_id  ID nhân viên. 0 = tất cả.
 *     @type string $date_from Ngày bắt đầu (Y-m-d, giờ site), tính cả ngày này.
 *     @type string $date_to   Ngày kết thúc (Y-m-d, giờ site), tính cả ngày này.
 *     @type string $search    Tìm trong tên khách hoặc số điện thoại.
 *     @type string $orderby   id|start_at|created_at|customer_name|status.
 *     @type string $order     ASC|DESC.
 *     @type int    $per_page  Số dòng mỗi trang (1–100).
 *     @type int    $paged     Trang hiện tại (từ 1).
 * }
 * @return array { items: object[], total: int }
 */
function salon_get_bookings( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'status'    => '',
			'staff_id'  => 0,
			'date_from' => '',
			'date_to'   => '',
			'search'    => '',
			'orderby'   => 'start_at',
			'order'     => 'DESC',
			'per_page'  => 20,
			'paged'     => 1,
		)
	);

	$table  = salon_bookings_table();
	$where  = array( '1=1' );
	$params = array();

	// 1. Mỗi điều kiện: thêm 1 mảnh SQL có placeholder + 1 giá trị vào $params (cùng thứ tự).
	if ( '' !== (string) $args['status'] ) {
		$where[]  = 'b.status = %s';
		$params[] = (string) $args['status'];
	}

	if ( (int) $args['staff_id'] > 0 ) {
		$where[]  = 'b.staff_id = %d';
		$params[] = (int) $args['staff_id'];
	}

	// 2. Lọc ngày: so sánh trực tiếp cột start_at với mốc UTC (không dùng DATE(start_at))
	//    → MySQL dùng được index, và đúng múi giờ Việt Nam.
	$from_utc = $args['date_from'] ? salon_local_day_start_utc( $args['date_from'] ) : null;
	if ( $from_utc ) {
		$where[]  = 'b.start_at >= %s';
		$params[] = $from_utc;
	}

	$to_utc = $args['date_to'] ? salon_local_day_start_utc( $args['date_to'], 1 ) : null; // đầu ngày hôm sau
	if ( $to_utc ) {
		$where[]  = 'b.start_at < %s';
		$params[] = $to_utc;
	}

	// 3. Tìm kiếm: esc_like() để ký tự % và _ của người dùng được hiểu là chữ, không phải wildcard.
	$search = trim( (string) $args['search'] );
	if ( '' !== $search ) {
		$like     = '%' . $wpdb->esc_like( $search ) . '%';
		$where[]  = '( b.customer_name LIKE %s OR b.phone LIKE %s )';
		$params[] = $like;
		$params[] = $like;
	}

	// 4. ORDER BY không dùng placeholder giá trị được → bắt buộc whitelist.
	$allowed_orderby = array( 'id', 'start_at', 'created_at', 'customer_name', 'status' );
	$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'start_at';
	$order           = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';

	// 5. Phân trang: ép kiểu + giới hạn để không ai yêu cầu 1 triệu dòng một lần.
	$per_page = min( 100, max( 1, (int) $args['per_page'] ) );
	$paged    = max( 1, (int) $args['paged'] );
	$offset   = ( $paged - 1 ) * $per_page;

	$where_sql = implode( ' AND ', $where );

	// Đếm tổng (cho phân trang). Không có điều kiện nào thì không có placeholder → không gọi prepare().
	$count_sql = "SELECT COUNT(*) FROM {$table} b WHERE {$where_sql}";
	if ( $params ) {
		$count_sql = $wpdb->prepare( $count_sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
	$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	// Lấy dữ liệu trang hiện tại, kèm tên dịch vụ & nhân viên (LEFT JOIN: post bị xoá vẫn hiện lịch hẹn).
	$items_sql = "SELECT b.*, s.post_title AS service_name, st.post_title AS staff_name
		FROM {$table} b
		LEFT JOIN {$wpdb->posts} s  ON s.ID  = b.service_id
		LEFT JOIN {$wpdb->posts} st ON st.ID = b.staff_id
		WHERE {$where_sql}
		ORDER BY b.{$orderby} {$order}, b.id DESC
		LIMIT %d OFFSET %d";

	$items = $wpdb->get_results(
		$wpdb->prepare( $items_sql, array_merge( $params, array( $per_page, $offset ) ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	);

	return array(
		'items' => $items ? $items : array(),
		'total' => $total,
	);
}

/**
 * Kiểm tra nhân viên có rảnh trong khoảng [start, end) hay không.
 *
 * Hai khoảng [s1, e1) và [s2, e2) chồng lấn khi và chỉ khi: s1 < e2 AND s2 < e1.
 * Lịch nối tiếp (9:00–9:45 rồi 9:45–10:30) KHÔNG bị coi là trùng.
 *
 * @param int    $staff_id   ID nhân viên.
 * @param string $start_utc  Giờ bắt đầu (UTC, Y-m-d H:i:s).
 * @param string $end_utc    Giờ kết thúc (UTC, Y-m-d H:i:s).
 * @param int    $exclude_id Bỏ qua lịch hẹn này (dùng khi admin sửa một lịch đã có).
 * @return bool true nếu rảnh. Dữ liệu sai hoặc lỗi DB → false (an toàn: thà báo bận).
 */
function salon_is_slot_available( $staff_id, $start_utc, $end_utc, $exclude_id = 0 ) {
	global $wpdb;

	$staff_id = (int) $staff_id;
	$start    = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', (string) $start_utc, new DateTimeZone( 'UTC' ) );
	$end      = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', (string) $end_utc, new DateTimeZone( 'UTC' ) );

	if ( $staff_id <= 0 || ! $start || ! $end || $start >= $end ) {
		return false;
	}

	$table    = salon_bookings_table();
	$conflict = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM {$table}
			WHERE staff_id = %d
			  AND status <> %s
			  AND start_at < %s
			  AND end_at > %s
			  AND id <> %d
			LIMIT 1",
			$staff_id,
			'cancelled',
			$end->format( 'Y-m-d H:i:s' ),   // lịch cũ bắt đầu TRƯỚC khi lịch mới kết thúc
			$start->format( 'Y-m-d H:i:s' ), // và kết thúc SAU khi lịch mới bắt đầu
			(int) $exclude_id
		)
	);

	// get_var() trả null cả khi "không có dòng nào" lẫn khi "SQL lỗi" → phải tách 2 trường hợp.
	if ( '' !== $wpdb->last_error ) {
		return false;
	}

	return null === $conflict;
}

/**
 * Danh sách trạng thái hợp lệ. Có filter để plugin khác thêm trạng thái (vd 'no_show').
 *
 * @return array<string,string> slug => nhãn hiển thị.
 */
function salon_booking_statuses() {
	return apply_filters(
		'salon_booking_statuses',
		array(
			'pending'   => 'Chờ xác nhận',
			'confirmed' => 'Đã xác nhận',
			'completed' => 'Hoàn thành',
			'cancelled' => 'Đã huỷ',
		)
	);
}

/**
 * Tạo lịch hẹn mới. Đây là điểm DUY NHẤT ghi lịch hẹn vào DB, nên hook đặt ở đây
 * thì mọi nơi tạo lịch (form, REST, admin) đều đi qua.
 *
 * Hooks:
 *  - filter 'salon_booking_data'    : sửa dữ liệu TRƯỚC khi lưu (chuẩn hoá SĐT...).
 *  - action 'salon_booking_created' : báo SAU khi lưu thành công (gửi mail, SMS, log...).
 *
 * Validate đầy đủ (dữ liệu từ người dùng) nằm ở salon_validate_booking_input() — Phần 7.
 * Hàm này vẫn giữ kiểm tra tối thiểu + kiểm tra trùng lịch NGAY TRƯỚC khi ghi.
 *
 * @param array $data customer_name, phone, email, service_id, staff_id, start_at, end_at (UTC), note.
 * @return int|WP_Error ID lịch hẹn mới hoặc lỗi.
 */
function salon_create_booking( array $data ) {
	global $wpdb;

	$data = wp_parse_args(
		$data,
		array(
			'customer_name' => '',
			'phone'         => '',
			'email'         => '',
			'service_id'    => 0,
			'staff_id'      => 0,
			'start_at'      => '',
			'end_at'        => '',
			'note'          => '',
			'status'        => 'pending',
		)
	);

	/**
	 * Lọc dữ liệu lịch hẹn trước khi kiểm tra và lưu.
	 *
	 * @param array $data Dữ liệu lịch hẹn.
	 */
	$data = apply_filters( 'salon_booking_data', $data );

	if ( '' === trim( (string) $data['customer_name'] ) || '' === trim( (string) $data['phone'] ) ) {
		return new WP_Error( 'salon_missing_fields', 'Thiếu tên hoặc số điện thoại.' );
	}
	if ( ! isset( salon_booking_statuses()[ $data['status'] ] ) ) {
		return new WP_Error( 'salon_invalid_status', 'Trạng thái không hợp lệ.' );
	}
	if ( ! salon_is_slot_available( $data['staff_id'], $data['start_at'], $data['end_at'] ) ) {
		return new WP_Error( 'salon_slot_taken', 'Nhân viên đã có lịch trong khung giờ này.' );
	}

	$ok = $wpdb->insert(
		salon_bookings_table(),
		array(
			'customer_name' => $data['customer_name'],
			'phone'         => $data['phone'],
			'email'         => (string) $data['email'],
			'service_id'    => (int) $data['service_id'],
			'staff_id'      => (int) $data['staff_id'],
			'start_at'      => $data['start_at'],
			'end_at'        => $data['end_at'],
			'note'          => $data['note'],
			'status'        => $data['status'],
			'created_at'    => current_time( 'mysql', true ),
		),
		array( '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
	);

	if ( false === $ok ) {
		return new WP_Error( 'salon_db_error', 'Không lưu được lịch hẹn.' );
	}

	$booking_id = (int) $wpdb->insert_id;

	/**
	 * Chạy sau khi lịch hẹn được lưu thành công.
	 *
	 * @param int   $booking_id ID lịch hẹn.
	 * @param array $data       Dữ liệu đã lưu.
	 */
	do_action( 'salon_booking_created', $booking_id, $data );

	return $booking_id;
}
