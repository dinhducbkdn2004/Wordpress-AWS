<?php
/**
 * Trang Settings → Salon Booking (giao diện). Đăng ký option + sanitize ở includes/settings.php.
 *
 * Luồng lưu của Settings API:
 *   form POST tới wp-admin/options.php
 *     → check_admin_referer( 'salon_settings_group-options' )   (nonce do settings_fields() sinh)
 *     → kiểm tra option có nằm trong allowed_options của group không
 *     → update_option( 'salon_settings', $_POST['salon_settings'] )
 *          → sanitize_option() → filter sanitize_option_salon_settings → salon_sanitize_settings()
 *     → redirect về trang này kèm ?settings-updated=true
 *
 * @package SalonBooking
 */

defined( 'ABSPATH' ) || exit;

const SALON_SETTINGS_PAGE = 'salon-booking'; // slug trang: options-general.php?page=salon-booking

add_action( 'admin_menu', 'salon_add_settings_page' );
add_action( 'admin_init', 'salon_add_settings_fields' );
add_filter( 'plugin_action_links_' . plugin_basename( SALON_FILE ), 'salon_plugin_action_links' );

function salon_add_settings_page() {
	add_options_page(
		'Cài đặt Salon Booking', // <title>
		'Salon Booking',         // tên trong menu Settings
		'manage_options',        // capability để THẤY menu và MỞ trang
		SALON_SETTINGS_PAGE,
		'salon_render_settings_page'
	);
}

function salon_add_settings_fields() {
	add_settings_section( 'salon_booking', 'Đặt lịch', '__return_false', SALON_SETTINGS_PAGE );

	add_settings_field(
		'salon_booking_page_id',
		'Trang đặt lịch',
		'salon_field_booking_page',
		SALON_SETTINGS_PAGE,
		'salon_booking',
		array( 'label_for' => 'salon_booking_page_id' ) // WP bọc tiêu đề trong <label for="..."> → click tiêu đề focus vào ô
	);
	add_settings_field( 'salon_hours', 'Giờ mở cửa', 'salon_field_hours', SALON_SETTINGS_PAGE, 'salon_booking' );
	add_settings_field(
		'salon_slot_minutes',
		'Bước chọn giờ',
		'salon_field_slot_minutes',
		SALON_SETTINGS_PAGE,
		'salon_booking',
		array( 'label_for' => 'salon_slot_minutes' )
	);
	add_settings_field(
		'salon_notify_email',
		'Email nhận thông báo',
		'salon_field_notify_email',
		SALON_SETTINGS_PAGE,
		'salon_booking',
		array( 'label_for' => 'salon_notify_email' )
	);

	add_settings_section( 'salon_advanced', 'Nâng cao', '__return_false', SALON_SETTINGS_PAGE );
	add_settings_field( 'salon_delete_data', 'Gỡ cài đặt', 'salon_field_delete_data', SALON_SETTINGS_PAGE, 'salon_advanced' );
}

/*
 * Mỗi field in ra input có name="salon_settings[key]" → PHP nhận $_POST['salon_settings'] là MẢNG,
 * khớp với 1 option dạng mảng. Mọi giá trị in vào HTML đều escape (esc_attr / selected / checked).
 */

function salon_field_booking_page() {
	wp_dropdown_pages(
		array(
			'name'              => 'salon_settings[booking_page_id]',
			'id'                => 'salon_booking_page_id',
			'selected'          => (int) salon_get_setting( 'booking_page_id' ),
			'show_option_none'  => '— Chưa chọn —',
			'option_none_value' => '0',
		)
	);
	echo '<p class="description">Trang chứa form đặt lịch. Theme dùng trang này cho các nút "Đặt lịch".</p>';
}

function salon_field_hours() {
	printf(
		'<input type="time" name="salon_settings[open_time]" value="%1$s" aria-label="Giờ mở cửa"> – <input type="time" name="salon_settings[close_time]" value="%2$s" aria-label="Giờ đóng cửa">',
		esc_attr( salon_get_setting( 'open_time' ) ),
		esc_attr( salon_get_setting( 'close_time' ) )
	);
	echo '<p class="description">Theo múi giờ của site (Settings → General).</p>';
}

function salon_field_slot_minutes() {
	$current = (int) salon_get_setting( 'slot_minutes' );
	echo '<select id="salon_slot_minutes" name="salon_settings[slot_minutes]">';
	foreach ( salon_allowed_slot_minutes() as $minutes ) {
		printf( '<option value="%1$d" %2$s>%1$d phút</option>', (int) $minutes, selected( $current, $minutes, false ) );
	}
	echo '</select>';
}

function salon_field_notify_email() {
	printf(
		'<input type="email" id="salon_notify_email" name="salon_settings[notify_email]" class="regular-text" value="%1$s" placeholder="%2$s">',
		esc_attr( salon_get_setting( 'notify_email' ) ),
		esc_attr( get_option( 'admin_email' ) )
	);
	echo '<p class="description">Để trống sẽ dùng email quản trị.</p>';
}

function salon_field_delete_data() {
	printf(
		'<label><input type="checkbox" name="salon_settings[delete_data_on_uninstall]" value="1" %s> Xoá TOÀN BỘ dữ liệu (lịch hẹn, dịch vụ, nhân viên, cài đặt) khi xoá plugin</label>',
		checked( (bool) salon_get_setting( 'delete_data_on_uninstall' ), true, false )
	);
	echo '<p class="description">Không tick: xoá plugin vẫn giữ dữ liệu, cài lại là dùng tiếp.</p>';
}

function salon_render_settings_page() {
	// Menu đã kiểm tra quyền, nhưng kiểm tra lại ở chỗ in ra là thói quen tốt (defense in depth).
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	// Không gọi settings_errors() ở đây: trang con của Settings đã được options-head.php in sẵn
	// (gọi thêm sẽ thấy mỗi thông báo xuất hiện 2 lần).
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'salon_settings_group' );    // hidden option_page + action + nonce
			do_settings_sections( SALON_SETTINGS_PAGE );  // in các section/field đã add ở trên
			submit_button( 'Lưu cài đặt' );
			?>
		</form>
	</div>
	<?php
}

/**
 * Thêm link "Cài đặt" dưới tên plugin ở màn hình Plugins.
 *
 * @param array $links Các link sẵn có (Deactivate...).
 * @return array
 */
function salon_plugin_action_links( $links ) {
	$url = admin_url( 'options-general.php?page=' . SALON_SETTINGS_PAGE );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">Cài đặt</a>' );
	return $links;
}
