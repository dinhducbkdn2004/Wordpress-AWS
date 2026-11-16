<?php
/**
 * Hệ thống hook "tự chế" bằng PHP thuần — rút gọn từ WP_Hook để thấy bản chất.
 * Chạy (đứng ở C:\xampp\htdocs\wordpress): C:\xampp\php\php.exe wp-content\plugins\salon-booking\dev\mini-hooks.php
 */

$hooks = array(); // tương đương global $wp_filter: [tên hook][priority][] = callback

function my_add_filter( $name, $callback, $priority = 10 ) {
	global $hooks;
	$hooks[ $name ][ $priority ][] = $callback;
	ksort( $hooks[ $name ] ); // priority nhỏ chạy trước (WP_Hook cũng ksort)
}

function my_apply_filters( $name, $value, ...$args ) {
	global $hooks;
	foreach ( $hooks[ $name ] ?? array() as $callbacks ) {
		foreach ( $callbacks as $cb ) {
			$value = $cb( $value, ...$args ); // FILTER: kết quả hàm này là đầu vào hàm sau
		}
	}
	return $value;
}

function my_do_action( $name, ...$args ) {
	global $hooks;
	foreach ( $hooks[ $name ] ?? array() as $callbacks ) {
		foreach ( $callbacks as $cb ) {
			$cb( ...$args ); // ACTION: bỏ qua giá trị trả về
		}
	}
}

// ---- "Plugin" đăng ký ----
my_add_filter( 'price', fn( $p ) => $p * 0.9, 20 );   // giảm 10%
my_add_filter( 'price', fn( $p ) => $p + 20000, 5 );  // cộng phí 20k — priority 5 nên chạy TRƯỚC
my_add_filter( 'booked', function ( $name ) { echo "Gửi SMS cho {$name}\n"; } );

// ---- "Core" gọi ----
echo 'Giá: ', my_apply_filters( 'price', 100000 ), "\n"; // (100000 + 20000) * 0.9 = 108000
my_do_action( 'booked', 'Chị Hoa' );
echo 'Hook chưa ai đăng ký: ', my_apply_filters( 'unknown', 'giữ nguyên' ), "\n";
