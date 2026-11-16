<?php
/**
 * Salon Theme — functions.php
 *
 * Được nạp ở wp-settings.php (sau plugin, trước init). Chỉ chứa code về GIAO DIỆN:
 * theme supports, menu, CSS/JS, hàm hiển thị. Dữ liệu & nghiệp vụ nằm trong plugin salon-booking.
 *
 * @package SalonTheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Khai báo tính năng theme hỗ trợ.
 * after_setup_theme: hook đầu tiên chạy SAU khi functions.php được nạp.
 */
add_action( 'after_setup_theme', 'salon_theme_setup' );
function salon_theme_setup() {
	add_theme_support( 'title-tag' );          // WP tự in <title> trong wp_head() — plugin SEO sửa được
	add_theme_support( 'post-thumbnails' );    // bật "Featured image"
	add_theme_support( 'custom-logo', array( 'height' => 60, 'width' => 200, 'flex-width' => true, 'flex-height' => true ) );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'responsive-embeds' );

	add_image_size( 'salon-card', 600, 400, true ); // ảnh card dịch vụ, cắt đúng tỉ lệ 3:2

	register_nav_menus(
		array(
			'primary' => 'Menu chính',
			'footer'  => 'Menu chân trang',
		)
	);
}

/**
 * Vùng widget (sidebar). Theme quyết định CÓ những vùng nào và BAO NGOÀI mỗi widget bằng HTML gì;
 * admin quyết định đặt widget/block nào vào (Appearance → Widgets). Widget cụ thể do plugin cung cấp.
 */
add_action( 'widgets_init', 'salon_theme_widgets_init' );
function salon_theme_widgets_init() {
	register_sidebar(
		array(
			'id'            => 'sidebar-service',          // ID cố định: đổi ID = widget đã đặt "mồ côi" sang Inactive
			'name'          => 'Sidebar dịch vụ',
			'description'   => 'Hiện ở trang chi tiết dịch vụ, dưới nút Đặt lịch.',
			'before_widget' => '<section id="%1$s" class="widget %2$s">', // %1$s = widget id, %2$s = class của widget
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
}

/**
 * Nạp CSS/JS. Không bao giờ viết <link>/<script> cứng trong header.php:
 * enqueue giúp tránh nạp trùng, khai báo phụ thuộc, và cho plugin cache/minify can thiệp.
 */
add_action( 'wp_enqueue_scripts', 'salon_theme_assets' );
function salon_theme_assets() {
	$dir = get_template_directory();      // đường dẫn file (để đọc filemtime)
	$uri = get_template_directory_uri();  // URL (để trình duyệt tải)

	// Version = thời điểm sửa file → sửa CSS là trình duyệt tải bản mới (cache busting).
	wp_enqueue_style( 'salon-main', $uri . '/assets/css/main.css', array(), filemtime( $dir . '/assets/css/main.css' ) );

	wp_enqueue_script(
		'salon-navigation',
		$uri . '/assets/js/navigation.js',
		array(),
		filemtime( $dir . '/assets/js/navigation.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}

/**
 * Menu dự phòng khi admin chưa gán menu vào vị trí "Menu chính".
 */
function salon_theme_menu_fallback() {
	$items = array( home_url( '/' ) => 'Trang chủ' );

	$services_url = get_post_type_archive_link( 'salon_service' ); // false nếu plugin chưa bật
	if ( $services_url ) {
		$items[ $services_url ] = 'Dịch vụ';
	}
	$items[ home_url( '/gioi-thieu-salon/' ) ] = 'Giới thiệu';

	echo '<ul id="primary-menu" class="menu">';
	foreach ( $items as $url => $label ) {
		printf( '<li><a href="%s">%s</a></li>', esc_url( $url ), esc_html( $label ) );
	}
	echo '</ul>';
}

/**
 * URL trang đặt lịch, có thể kèm dịch vụ chọn sẵn.
 * Trang do admin chọn ở Settings → Salon Booking → lấy qua API của plugin (không hardcode slug:
 * admin đổi slug trang thành /booking/ thì nút vẫn đúng).
 *
 * @param int $service_id ID dịch vụ (0 = không chọn sẵn).
 * @return string
 */
function salon_theme_booking_url( $service_id = 0 ) {
	$url = function_exists( 'salon_get_booking_url' ) ? salon_get_booking_url( array( 'service' => $service_id ) ) : '';

	// Plugin tắt hoặc admin chưa chọn trang → về trang chủ thay vì link hỏng.
	return $url ? $url : home_url( '/' );
}

/**
 * Lấy giá & thời lượng dịch vụ qua API của plugin.
 * function_exists(): theme vẫn chạy (không fatal) nếu ai đó tắt plugin.
 *
 * @param int $post_id ID dịch vụ.
 * @return array{price:int,duration:int}|null
 */
function salon_theme_service_details( $post_id ) {
	if ( ! function_exists( 'salon_get_service_details' ) ) {
		return null;
	}
	return salon_get_service_details( $post_id );
}

/**
 * In giá + thời lượng dạng "150.000đ · 45 phút".
 *
 * @param int $post_id ID dịch vụ.
 */
function salon_theme_service_meta( $post_id ) {
	$details = salon_theme_service_details( $post_id );
	if ( ! $details ) {
		return;
	}
	echo '<p class="service-meta">';
	if ( $details['price'] ) {
		echo '<span class="service-price">' . esc_html( salon_format_price( $details['price'] ) ) . '</span>';
	}
	if ( $details['duration'] ) {
		echo '<span class="service-duration">' . esc_html( $details['duration'] . ' phút' ) . '</span>';
	}
	echo '</p>';
}
