<?php
/**
 * Màn hình sửa Dịch vụ: meta box "Giá & thời lượng" + cột trong bảng danh sách.
 *
 * Luồng lưu (Block Editor):
 *   1. Bấm "Update" → JS gửi REST PUT /wp/v2/services/14 → wp_insert_post() → save_post_salon_service
 *      (request này KHÔNG có $_POST của meta box → handler thấy thiếu nonce → bỏ qua)
 *   2. Ngay sau đó JS POST form meta box tới post.php?meta-box-loader=1 → save_post_salon_service lần 2
 *      (có nonce → lưu meta)
 *
 * @package SalonBooking
 */

defined( 'ABSPATH' ) || exit;

// "add_meta_boxes_{post_type}" chỉ bắn ở màn hình sửa CPT này → không cần tự kiểm tra post type.
add_action( 'add_meta_boxes_salon_service', 'salon_add_service_meta_box' );

// "save_post_{post_type}" chỉ bắn cho đúng CPT này. Revision/autosave có post_type = 'revision'
// nên KHÔNG kích hoạt hook này (khác với 'save_post' chung — bắn cho mọi thứ).
add_action( 'save_post_salon_service', 'salon_save_service_meta', 10, 2 );

function salon_add_service_meta_box() {
	add_meta_box(
		'salon_service_details',          // id HTML
		'Giá & thời lượng',               // tiêu đề
		'salon_render_service_meta_box',  // hàm in nội dung
		'salon_service',                  // màn hình
		'side',                           // vị trí: normal | side | advanced
		'high'
	);
}

/**
 * @param WP_Post $post Dịch vụ đang sửa.
 */
function salon_render_service_meta_box( $post ) {
	$details = salon_get_service_details( $post->ID );

	// Nonce gắn với ID post → nonce của dịch vụ A không dùng để sửa dịch vụ B được.
	wp_nonce_field( 'salon_save_service_' . $post->ID, 'salon_service_nonce' );
	?>
	<p>
		<label for="salon_price"><strong>Giá (VND)</strong></label><br>
		<input type="number" id="salon_price" name="salon_price" class="widefat"
			min="0" max="100000000" step="1000"
			value="<?php echo esc_attr( $details['price'] ); ?>">
	</p>
	<p>
		<label for="salon_duration"><strong>Thời lượng (phút)</strong></label><br>
		<input type="number" id="salon_duration" name="salon_duration" class="widefat"
			min="5" max="480" step="5"
			value="<?php echo esc_attr( $details['duration'] ); ?>">
	</p>
	<p class="description">Thời lượng dùng để tính giờ kết thúc và kiểm tra trùng lịch.</p>
	<?php
	// min/max/step trên HTML chỉ giúp người dùng — kẻ xấu gửi request thẳng sẽ bỏ qua.
	// Validate THẬT nằm ở salon_save_service_meta().
}

/**
 * Lưu meta. Thứ tự kiểm tra: có phải form của mình? → nonce (CSRF) → quyền → sanitize/validate → lưu.
 *
 * @param int     $post_id ID dịch vụ.
 * @param WP_Post $post    Đối tượng post.
 */
function salon_save_service_meta( $post_id, $post ) {
	// 1. Không có nonce = request không đến từ form meta box (REST, Quick Edit, wp_insert_post trong code...) → bỏ qua.
	if ( ! isset( $_POST['salon_service_nonce'] ) ) {
		return;
	}

	// 2. CSRF: form phải do chính WP sinh cho user này, cho đúng post này.
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['salon_service_nonce'] ) ), 'salon_save_service_' . $post_id ) ) {
		return;
	}

	// 3. Autosave: chỉ lưu nháp nội dung, không đụng meta.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// 4. Quyền: nonce chỉ chứng minh "ý định", KHÔNG chứng minh "được phép".
	//    'edit_post' là meta capability → map_meta_cap đổi thành edit_posts / edit_others_posts / edit_published_posts...
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// 5. Sanitize + validate từng field. Sai thì bỏ qua field đó (giữ giá trị cũ).
	if ( isset( $_POST['salon_price'] ) ) {
		$price = absint( wp_unslash( $_POST['salon_price'] ) );
		if ( $price <= 100000000 ) {
			update_post_meta( $post_id, '_salon_price', $price );
		}
	}

	if ( isset( $_POST['salon_duration'] ) ) {
		$duration = absint( wp_unslash( $_POST['salon_duration'] ) );
		if ( $duration >= 5 && $duration <= 480 && 0 === $duration % 5 ) {
			update_post_meta( $post_id, '_salon_duration', $duration );
		}
	}
}

/*
 * ---------------------------------------------------------------------------------
 * Cột Giá / Thời lượng trong Dịch vụ → Tất cả dịch vụ (edit.php?post_type=salon_service)
 * ---------------------------------------------------------------------------------
 */

add_filter( 'manage_salon_service_posts_columns', 'salon_service_columns' );
add_action( 'manage_salon_service_posts_custom_column', 'salon_service_column_content', 10, 2 );
add_filter( 'manage_edit-salon_service_sortable_columns', 'salon_service_sortable_columns' );
add_action( 'pre_get_posts', 'salon_service_admin_orderby' );

/**
 * Chèn 2 cột trước cột "Ngày".
 *
 * @param array $columns key => tiêu đề.
 * @return array
 */
function salon_service_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		if ( 'date' === $key ) {
			$new['salon_price']    = 'Giá';
			$new['salon_duration'] = 'Thời lượng';
		}
		$new[ $key ] = $label;
	}
	return $new;
}

/**
 * @param string $column  Key cột.
 * @param int    $post_id ID dịch vụ.
 */
function salon_service_column_content( $column, $post_id ) {
	$details = salon_get_service_details( $post_id );

	if ( 'salon_price' === $column ) {
		echo esc_html( salon_format_price( $details['price'] ) );
	} elseif ( 'salon_duration' === $column ) {
		echo esc_html( $details['duration'] . ' phút' );
	}
}

/**
 * Cho phép bấm tiêu đề cột "Giá" để sắp xếp. Chỉ thêm ?orderby=salon_price vào URL,
 * WP KHÔNG tự biết sắp theo meta → phải xử lý ở pre_get_posts bên dưới.
 *
 * @param array $columns Cột sắp xếp được.
 * @return array
 */
function salon_service_sortable_columns( $columns ) {
	$columns['salon_price'] = 'salon_price';
	return $columns;
}

/**
 * @param WP_Query $query Query sắp chạy.
 */
function salon_service_admin_orderby( $query ) {
	// Chỉ main query của màn hình admin — không đụng query của widget, menu, frontend...
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( 'salon_service' !== $query->get( 'post_type' ) || 'salon_price' !== $query->get( 'orderby' ) ) {
		return;
	}

	// meta_key + orderby meta_value_num → JOIN wp_postmeta, ORDER BY CAST(meta_value AS SIGNED).
	// Lưu ý: JOIN này loại các dịch vụ CHƯA có meta _salon_price khỏi danh sách.
	$query->set( 'meta_key', '_salon_price' );
	$query->set( 'orderby', 'meta_value_num' );
}
