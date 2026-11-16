<?php
/**
 * Mô hình dữ liệu nội dung: CPT Dịch vụ / Nhân viên, taxonomy Loại dịch vụ, meta giá & thời lượng.
 *
 * Tất cả đăng ký ở 'init': lúc này $wp_rewrite đã có (cần cho rewrite slug) và
 * vẫn trước khi WP parse URL (wp()) → URL /dich-vu/... nhận ra được.
 *
 * @package SalonBooking
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'salon_register_post_types' );
add_action( 'init', 'salon_register_taxonomies' );
add_action( 'init', 'salon_register_meta' );

function salon_register_post_types() {
	register_post_type(
		'salon_service', // ≤ 20 ký tự, có prefix để không đụng CPT của plugin khác
		array(
			'labels'        => array(
				'name'               => 'Dịch vụ',
				'singular_name'      => 'Dịch vụ',
				'menu_name'          => 'Dịch vụ',
				'add_new'            => 'Thêm mới',
				'add_new_item'       => 'Thêm dịch vụ',
				'edit_item'          => 'Sửa dịch vụ',
				'new_item'           => 'Dịch vụ mới',
				'view_item'          => 'Xem dịch vụ',
				'view_items'         => 'Xem các dịch vụ',
				'search_items'       => 'Tìm dịch vụ',
				'not_found'          => 'Chưa có dịch vụ nào.',
				'not_found_in_trash' => 'Thùng rác trống.',
				'all_items'          => 'Tất cả dịch vụ',
				'archives'           => 'Danh sách dịch vụ',
				'featured_image'     => 'Ảnh dịch vụ',
				'set_featured_image' => 'Chọn ảnh dịch vụ',
			),
			'public'        => true,                  // hiện ở frontend + admin + tìm kiếm
			'has_archive'   => true,                  // /dich-vu/ → archive-salon_service.php
			'rewrite'       => array(
				'slug'       => 'dich-vu',              // /dich-vu/son-gel/
				'with_front' => false,                  // không ghép phần "front" của permalink (vd /blog/)
			),
			'menu_position' => 25,                    // ngay dưới Comments
			'menu_icon'     => 'dashicons-art',
			// page-attributes = ô "Order" để sắp xếp.
			// custom-fields: BẮT BUỘC để REST trả field "meta" (class-wp-rest-posts-controller.php: case 'custom-fields'),
			// dù meta đã register show_in_rest. Key "_salon_*" là protected nên vẫn không lộ ra hộp Custom Fields.
			'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'custom-fields' ),
			'show_in_rest'  => true,                  // bắt buộc để dùng Block Editor + REST /wp/v2/salon_service
			'rest_base'     => 'services',            // /wp/v2/services thay vì /wp/v2/salon_service
			// 'capability_type' => 'post' (mặc định): ai sửa được Post thì sửa được Dịch vụ. Phần 9 sẽ tách quyền riêng.
		)
	);

	register_post_type(
		'salon_staff',
		array(
			'labels'        => array(
				'name'               => 'Nhân viên',
				'singular_name'      => 'Nhân viên',
				'add_new'            => 'Thêm mới',
				'add_new_item'       => 'Thêm nhân viên',
				'edit_item'          => 'Sửa nhân viên',
				'view_item'          => 'Xem nhân viên',
				'search_items'       => 'Tìm nhân viên',
				'not_found'          => 'Chưa có nhân viên nào.',
				'not_found_in_trash' => 'Thùng rác trống.',
				'all_items'          => 'Tất cả nhân viên',
				'featured_image'     => 'Ảnh đại diện',
				'set_featured_image' => 'Chọn ảnh đại diện',
			),
			'public'        => true,
			'has_archive'   => false,
			'rewrite'       => array(
				'slug'       => 'nhan-vien',
				'with_front' => false,
			),
			'menu_position' => 26,
			'menu_icon'     => 'dashicons-groups',
			'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
			'show_in_rest'  => true,
			'rest_base'     => 'staff',
		)
	);
}

function salon_register_taxonomies() {
	register_taxonomy(
		'salon_service_cat',
		array( 'salon_service' ), // gắn vào CPT nào
		array(
			'labels'            => array(
				'name'          => 'Loại dịch vụ',
				'singular_name' => 'Loại dịch vụ',
				'menu_name'     => 'Loại dịch vụ',
				'all_items'     => 'Tất cả loại',
				'edit_item'     => 'Sửa loại dịch vụ',
				'add_new_item'  => 'Thêm loại dịch vụ',
				'parent_item'   => 'Loại cha',
				'search_items'  => 'Tìm loại dịch vụ',
				'not_found'     => 'Chưa có loại nào.',
			),
			'hierarchical'      => true,  // true = giống Category (có cha/con, chọn bằng checkbox); false = giống Tag
			'public'            => true,
			'show_admin_column' => true,  // thêm cột "Loại dịch vụ" vào bảng danh sách dịch vụ
			'show_in_rest'      => true,  // bắt buộc để hiện panel chọn loại trong Block Editor
			'rewrite'           => array(
				'slug'         => 'loai-dich-vu', // /loai-dich-vu/mong-tay/
				'with_front'   => false,
				'hierarchical' => true,           // /loai-dich-vu/cha/con/
			),
		)
	);
}

/**
 * Khai báo meta với WordPress: kiểu dữ liệu, sanitize, ai được sửa, có ra REST không.
 * Không đăng ký thì meta vẫn lưu được (EAV — Phần 2), nhưng không có schema, không ra REST.
 *
 * Key có dấu "_" ở đầu = "protected meta": bị ẩn khỏi hộp Custom Fields mặc định
 * → admin chỉ sửa qua meta box của mình (có validate).
 */
function salon_register_meta() {
	$common = array(
		'type'              => 'integer',
		'single'            => true,      // 1 giá trị / post (get_post_meta(..., true))
		'default'           => 0,
		'sanitize_callback' => 'absint',  // chạy trong update_post_meta() → chặn cả code khác ghi bậy
		'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
			return current_user_can( 'edit_post', $post_id ); // bắt buộc với protected meta khi ghi qua REST
		},
		'show_in_rest'      => true,      // xuất hiện trong /wp/v2/services → "meta": {...} (Phần 8)
	);

	register_post_meta( 'salon_service', '_salon_price', $common + array( 'description' => 'Giá (VND)' ) );
	register_post_meta( 'salon_service', '_salon_duration', $common + array( 'description' => 'Thời lượng (phút)' ) );
}

/**
 * API cho theme: lấy thông tin dịch vụ. Theme gọi hàm này thay vì tự đọc meta key,
 * để nếu plugin đổi cách lưu (vd chuyển sang bảng riêng) thì theme không phải sửa.
 *
 * @param int $post_id ID dịch vụ.
 * @return array{price:int,duration:int}
 */
function salon_get_service_details( $post_id ) {
	return array(
		'price'    => (int) get_post_meta( $post_id, '_salon_price', true ),
		'duration' => (int) get_post_meta( $post_id, '_salon_duration', true ),
	);
}

/**
 * Định dạng giá kiểu Việt Nam: 150000 → "150.000đ".
 *
 * @param int $price Giá (VND).
 * @return string
 */
function salon_format_price( $price ) {
	return number_format( (int) $price, 0, ',', '.' ) . 'đ';
}
