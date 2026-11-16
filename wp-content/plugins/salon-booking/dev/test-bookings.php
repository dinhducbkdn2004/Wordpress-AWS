<?php
/**
 * Test salon_get_bookings() và salon_is_slot_available().
 *
 * Chạy (đứng ở C:\xampp\htdocs\wordpress): C:\xampp\php\php.exe wp-content\plugins\salon-booking\dev\test-bookings.php
 *
 * Dữ liệu test được insert trong 1 TRANSACTION rồi ROLLBACK → DB thật không đổi.
 */
if ( PHP_SAPI !== 'cli' ) {
	exit;
}
require dirname( __DIR__, 4 ) . '/wp-load.php';
global $wpdb;

$pass = 0;
$fail = 0;
function check( $label, $expected, $actual ) {
	global $pass, $fail;
	if ( $expected === $actual ) {
		$pass++;
		echo "  ✔ {$label}\n";
	} else {
		$fail++;
		echo "  ✘ {$label}\n      mong đợi: " . var_export( $expected, true ) . "\n      thực tế:  " . var_export( $actual, true ) . "\n";
	}
}
function names( $result ) {
	return wp_list_pluck( $result['items'], 'customer_name' );
}

$table = salon_bookings_table();
$wpdb->query( 'START TRANSACTION' );

// ---- Dữ liệu mẫu (giờ UTC; VN = UTC+7) ---------------------------------
$fixtures = array(
	//           tên                 SĐT           staff   start (UTC)            end (UTC)              status
	'A' => array( 'Nguyễn Văn Test', '0900000001', 900001, '2026-12-01 02:00:00', '2026-12-01 02:45:00', 'pending' ),   // 9:00 VN 1/12
	'B' => array( 'Trần Thị Test', '0900000002', 900001, '2026-12-01 03:00:00', '2026-12-01 03:30:00', 'confirmed' ),     // 10:00 VN 1/12
	'C' => array( 'Lê 100% Test', '0900000003', 900002, '2026-12-02 16:30:00', '2026-12-02 17:00:00', 'cancelled' ),      // 23:30 VN 2/12
	'D' => array( 'Phạm Test', '0900000004', 900001, '2026-11-30 17:30:00', '2026-11-30 18:00:00', 'pending' ),           // 0:30 VN 1/12 (!)
	'E' => array( 'Ngô 1000 Test', '0900000005', 900002, '2026-12-03 02:00:00', '2026-12-03 03:00:00', 'pending' ),
);
$ids = array();
foreach ( $fixtures as $key => $f ) {
	$wpdb->insert(
		$table,
		array(
			'customer_name' => $f[0],
			'phone'         => $f[1],
			'service_id'    => 0,
			'staff_id'      => $f[2],
			'start_at'      => $f[3],
			'end_at'        => $f[4],
			'status'        => $f[5],
			'created_at'    => current_time( 'mysql', true ),
		),
		array( '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
	);
	$ids[ $key ] = (int) $wpdb->insert_id;
}

echo "\n== salon_get_bookings() ==\n";

$r = salon_get_bookings( array( 'status' => 'pending', 'search' => 'Test', 'orderby' => 'start_at', 'order' => 'ASC' ) );
check( 'Lọc status=pending + search', array( 'Phạm Test', 'Nguyễn Văn Test', 'Ngô 1000 Test' ), names( $r ) );

$r = salon_get_bookings( array( 'staff_id' => 900001 ) );
check( 'Lọc staff_id → total = 3', 3, $r['total'] );

$r = salon_get_bookings( array( 'date_from' => '2026-12-01', 'date_to' => '2026-12-01', 'search' => 'Test', 'orderby' => 'start_at', 'order' => 'ASC' ) );
check( 'Ngày 1/12 (giờ VN) gồm cả lịch 0:30 sáng = 17:30 UTC hôm trước', array( 'Phạm Test', 'Nguyễn Văn Test', 'Trần Thị Test' ), names( $r ) );

$r = salon_get_bookings( array( 'date_from' => '2026-12-02', 'date_to' => '2026-12-02', 'search' => 'Test' ) );
check( 'Ngày 2/12 gồm lịch 23:30 VN (= 16:30 UTC)', array( 'Lê 100% Test' ), names( $r ) );

$r = salon_get_bookings( array( 'search' => '100%' ) );
check( "esc_like: '100%' chỉ khớp '100%', không khớp '1000'", array( 'Lê 100% Test' ), names( $r ) );

$r = salon_get_bookings( array( 'search' => '0900000002' ) );
check( 'Tìm theo số điện thoại', array( 'Trần Thị Test' ), names( $r ) );

$r = salon_get_bookings( array( 'staff_id' => 900001, 'orderby' => 'start_at', 'order' => 'ASC', 'per_page' => 2, 'paged' => 1 ) );
check( 'Phân trang trang 1', array( 'Phạm Test', 'Nguyễn Văn Test' ), names( $r ) );
$r = salon_get_bookings( array( 'staff_id' => 900001, 'orderby' => 'start_at', 'order' => 'ASC', 'per_page' => 2, 'paged' => 2 ) );
check( 'Phân trang trang 2 (total vẫn = 3)', array( array( 'Trần Thị Test' ), 3 ), array( names( $r ), $r['total'] ) );

$r = salon_get_bookings( array( 'search' => "' OR '1'='1" ) );
check( 'SQL injection qua search → 0 dòng', 0, $r['total'] );

$r = salon_get_bookings( array( 'staff_id' => 900001, 'orderby' => 'id; DROP TABLE wp_users', 'paged' => -5 ) );
check( 'orderby lạ + paged âm → không lỗi SQL, về mặc định', array( '', 3 ), array( $wpdb->last_error, $r['total'] ) );

$r = salon_get_bookings( array( 'date_from' => '2026-02-31', 'staff_id' => 900001 ) );
check( 'Ngày không tồn tại (31/2) bị bỏ qua', 3, $r['total'] );

echo "\n== salon_is_slot_available() — staff 900001 có A 02:00–02:45, B 03:00–03:30 (UTC) ==\n";

check( 'Nằm gọn trong A (02:15–02:30) → bận', false, salon_is_slot_available( 900001, '2026-12-01 02:15:00', '2026-12-01 02:30:00' ) );
check( 'Chồng đầu A (01:30–02:15) → bận', false, salon_is_slot_available( 900001, '2026-12-01 01:30:00', '2026-12-01 02:15:00' ) );
check( 'Chồng cuối A (02:30–02:50) → bận', false, salon_is_slot_available( 900001, '2026-12-01 02:30:00', '2026-12-01 02:50:00' ) );
check( 'Bao trùm A và B (01:00–04:00) → bận', false, salon_is_slot_available( 900001, '2026-12-01 01:00:00', '2026-12-01 04:00:00' ) );
check( 'Khe giữa A và B (02:45–03:00) → rảnh', true, salon_is_slot_available( 900001, '2026-12-01 02:45:00', '2026-12-01 03:00:00' ) );
check( 'Kết thúc đúng lúc A bắt đầu (01:00–02:00) → rảnh', true, salon_is_slot_available( 900001, '2026-12-01 01:00:00', '2026-12-01 02:00:00' ) );
check( 'Trùng lịch C nhưng C đã cancelled → rảnh', true, salon_is_slot_available( 900002, '2026-12-02 16:30:00', '2026-12-02 17:00:00' ) );
check( 'Sửa chính lịch A (exclude A) → rảnh', true, salon_is_slot_available( 900001, '2026-12-01 02:00:00', '2026-12-01 02:45:00', $ids['A'] ) );
check( 'end <= start → false', false, salon_is_slot_available( 900001, '2026-12-01 05:00:00', '2026-12-01 05:00:00' ) );
check( 'Sai định dạng → false', false, salon_is_slot_available( 900001, '1/12/2026 9h', '2026-12-01 05:00:00' ) );

$wpdb->query( 'ROLLBACK' );

echo "\nKết quả: {$pass} đạt, {$fail} lỗi. Số query: {$wpdb->num_queries}\n";
echo 'Còn dữ liệu test trong DB? ' . $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE staff_id IN (900001, 900002)" ) . " dòng\n";
