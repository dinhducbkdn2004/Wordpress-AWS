<?php
/**
 * Quan sát hook của salon-booking + salon-hooks-lab.
 * Chạy (đứng ở C:\xampp\htdocs\wordpress): C:\xampp\php\php.exe wp-content\plugins\salon-booking\dev\test-hooks.php
 * Dữ liệu tạo trong TRANSACTION rồi ROLLBACK → DB thật không đổi.
 */
if ( PHP_SAPI !== 'cli' ) {
	exit;
}
require dirname( __DIR__, 4 ) . '/wp-load.php';
global $wpdb, $wp_filter;

echo "== 1. Sổ đăng ký: \$wp_filter['salon_booking_data'] ==\n";
foreach ( $wp_filter['salon_booking_data']->callbacks as $priority => $callbacks ) {
	foreach ( $callbacks as $idx => $cb ) {
		echo "  priority {$priority} | id: {$idx} | accepted_args: {$cb['accepted_args']}\n";
	}
}

$wpdb->query( 'START TRANSACTION' );

echo "\n== 2. Tạo lịch với dữ liệu 'bẩn' ==\n";
$input = array(
	'customer_name' => '  nguyễn thị hoa ',
	'phone'         => '+84 912-345-678',
	'staff_id'      => 900001,
	'start_at'      => '2026-12-05 02:00:00',
	'end_at'        => '2026-12-05 03:00:00',
);
$id  = salon_create_booking( $input );
$row = $wpdb->get_row( $wpdb->prepare( 'SELECT customer_name, phone, status FROM ' . salon_bookings_table() . ' WHERE id = %d', $id ) );
echo "  Đầu vào : '{$input['customer_name']}' / '{$input['phone']}'\n";
echo "  Đã lưu  : '{$row->customer_name}' / '{$row->phone}' / {$row->status}\n";
echo '  did_action(salon_booking_created) = ', did_action( 'salon_booking_created' ), "\n";

echo "\n== 3. Đặt trùng giờ → WP_Error, action KHÔNG chạy ==\n";
$err = salon_create_booking( array_merge( $input, array( 'start_at' => '2026-12-05 02:30:00', 'end_at' => '2026-12-05 03:30:00' ) ) );
echo '  ', is_wp_error( $err ) ? $err->get_error_code() . ': ' . $err->get_error_message() : 'KHÔNG lỗi?!', "\n";
echo '  did_action(salon_booking_created) = ', did_action( 'salon_booking_created' ), "\n";

echo "\n== 4. Filter thêm trạng thái ==\n";
echo '  ', implode( ', ', array_keys( salon_booking_statuses() ) ), "\n";

echo "\n== 5. remove_filter: phải đúng tên hàm VÀ đúng priority ==\n";
var_dump( remove_filter( 'salon_booking_data', 'salon_lab_normalize_phone' ) );    // priority mặc định 10 → false
var_dump( remove_filter( 'salon_booking_data', 'salon_lab_normalize_phone', 5 ) ); // đúng priority → true
$id2  = salon_create_booking( array_merge( $input, array( 'start_at' => '2026-12-06 02:00:00', 'end_at' => '2026-12-06 03:00:00' ) ) );
$row2 = $wpdb->get_row( $wpdb->prepare( 'SELECT phone FROM ' . salon_bookings_table() . ' WHERE id = %d', $id2 ) );
echo "  SĐT khi đã gỡ filter chuẩn hoá: '{$row2->phone}'\n";

$wpdb->query( 'ROLLBACK' );
echo "\n(Đã ROLLBACK. Xem dòng [salon] trong wp-content/debug.log)\n";
