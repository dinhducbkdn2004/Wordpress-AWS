<?php
/**
 * Tạo dữ liệu mẫu: dịch vụ, nhân viên, trang "Đặt lịch". Chạy lại nhiều lần không bị trùng
 * (kiểm tra theo slug trước khi tạo).
 *
 * Chạy (đứng ở C:\xampp\htdocs\wordpress):
 *   C:\xampp\php\php.exe wp-content\plugins\salon-booking\dev\seed-demo.php
 */
if ( PHP_SAPI !== 'cli' ) {
	exit;
}
require dirname( __DIR__, 4 ) . '/wp-load.php';

/**
 * Tạo post nếu slug chưa tồn tại, ngược lại cập nhật. Trả về ID.
 */
function seed_upsert( $post_type, $slug, $args, $meta = array() ) {
	$existing = get_page_by_path( $slug, OBJECT, $post_type );

	$postarr = array_merge(
		array(
			'post_type'   => $post_type,
			'post_name'   => $slug,
			'post_status' => 'publish',
			'meta_input'  => $meta,
		),
		$args
	);

	if ( $existing ) {
		$postarr['ID'] = $existing->ID;
		$id            = wp_update_post( $postarr, true );
		$action        = 'cập nhật';
	} else {
		$id     = wp_insert_post( $postarr, true );
		$action = 'tạo mới ';
	}

	if ( is_wp_error( $id ) ) {
		echo "  ✘ {$slug}: " . $id->get_error_message() . "\n";
		return 0;
	}
	echo "  ✔ {$action} {$post_type} #{$id} /{$slug}/\n";
	return $id;
}

echo "== Loại dịch vụ (taxonomy salon_service_cat) ==\n";
$service_cats = array(
	'mong-tay'     => 'Móng tay',
	'mong-chan'    => 'Móng chân',
	'cham-soc-toc' => 'Chăm sóc tóc',
);
foreach ( $service_cats as $cat_slug => $cat_name ) {
	if ( term_exists( $cat_slug, 'salon_service_cat' ) ) {
		echo "  ✔ đã có    term /{$cat_slug}/\n";
		continue;
	}
	$term = wp_insert_term( $cat_name, 'salon_service_cat', array( 'slug' => $cat_slug ) );
	echo is_wp_error( $term ) ? "  ✘ {$cat_slug}: " . $term->get_error_message() . "\n" : "  ✔ tạo mới  term #{$term['term_id']} /{$cat_slug}/\n";
}

echo "== Dịch vụ ==\n";
$services = array(
	array( 'son-gel', 'Sơn gel', 150000, 45, 'Sơn gel Hàn Quốc bền màu 3–4 tuần, hơn 200 mã màu.', 'mong-tay' ),
	array( 'noi-mong-up', 'Nối móng úp', 350000, 90, 'Móng úp form chuẩn, nhẹ tay, tự nhiên như móng thật.', 'mong-tay' ),
	array( 've-mong-nghe-thuat', 'Vẽ móng nghệ thuật', 200000, 60, 'Vẽ tay theo mẫu hoặc theo ý tưởng riêng của bạn.', 'mong-tay' ),
	array( 'manicure', 'Chăm sóc móng tay', 120000, 40, 'Cắt da, dũa form, dưỡng ẩm và massage tay.', 'mong-tay' ),
	array( 'pedicure', 'Chăm sóc móng chân', 180000, 60, 'Ngâm thảo dược, chà gót, cắt da và massage chân.', 'mong-chan' ),
	array( 'goi-dau-duong-sinh', 'Gội đầu dưỡng sinh', 150000, 60, 'Gội thảo mộc kết hợp bấm huyệt cổ vai gáy.', 'cham-soc-toc' ),
);
foreach ( $services as $i => list( $slug, $title, $price, $duration, $excerpt, $cat_slug ) ) {
	$service_id = seed_upsert(
		'salon_service',
		$slug,
		array(
			'post_title'   => $title,
			'post_excerpt' => $excerpt,
			'post_content' => "<!-- wp:paragraph --><p>{$excerpt}</p><!-- /wp:paragraph -->\n"
				. '<!-- wp:list --><ul class="wp-block-list"><li>Tư vấn miễn phí trước khi làm</li><li>Dụng cụ tiệt trùng</li><li>Bảo hành 7 ngày</li></ul><!-- /wp:list -->',
			// post_date lệch nhau để thứ tự hiển thị ổn định (mới nhất trước).
			'post_date'    => gmdate( 'Y-m-d H:i:s', strtotime( '2026-11-01 08:00:00' ) + ( count( $services ) - $i ) * 3600 ),
		),
		array(
			'_salon_price'    => $price,
			'_salon_duration' => $duration,
		)
	);
	if ( $service_id ) {
		// Ghi vào wp_term_relationships; false = THAY THẾ (không cộng dồn) → chạy lại không bị trùng.
		wp_set_object_terms( $service_id, $cat_slug, 'salon_service_cat', false );
	}
}

echo "== Nhân viên ==\n";
$staff = array(
	array( 'chi-lan', 'Chị Lan', 'Trưởng kỹ thuật — 8 năm kinh nghiệm nối móng' ),
	array( 'chi-mai', 'Chị Mai', 'Chuyên vẽ móng nghệ thuật' ),
	array( 'anh-tuan', 'Anh Tuấn', 'Gội dưỡng sinh & massage' ),
);
foreach ( $staff as list( $slug, $title, $excerpt ) ) {
	seed_upsert( 'salon_staff', $slug, array( 'post_title' => $title, 'post_excerpt' => $excerpt ) );
}

echo "== Trang ==\n";
$booking_page_id = seed_upsert(
	'page',
	'dat-lich',
	array(
		'post_title'   => 'Đặt lịch',
		'post_content' => '<!-- wp:paragraph --><p>Form đặt lịch sẽ được xây dựng ở Phần 7.</p><!-- /wp:paragraph -->',
	)
);

// Gán trang vừa tạo vào Settings → Salon Booking (nếu admin chưa chọn trang nào).
// update_option đi qua salon_sanitize_settings() vì option đã register_setting ở init.
if ( $booking_page_id && ! salon_get_setting( 'booking_page_id' ) ) {
	update_option( 'salon_settings', array_merge( salon_get_settings(), array( 'booking_page_id' => $booking_page_id ) ) );
	echo "  ✔ Settings: booking_page_id = {$booking_page_id}\n";
}

// Rewrite rules chỉ được tạo lại khi flush. CPT mới có slug /dich-vu/ → cần flush 1 lần.
// (Tương đương vào Settings → Permalinks bấm Save.) KHÔNG gọi hàm này ở mỗi request: rất tốn.
flush_rewrite_rules( false ); // false = không ghi lại .htaccess (không cần)
echo "\nĐã flush rewrite rules.\n";
