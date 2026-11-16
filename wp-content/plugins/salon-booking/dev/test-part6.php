<?php
/**
 * Kiểm tra Phần 6: shortcode, block động, widget, sidebar. Chỉ đọc — không ghi DB.
 *
 * Chạy (đứng ở C:\xampp\htdocs\wordpress):
 *   C:\xampp\php\php.exe wp-content\plugins\salon-booking\dev\test-part6.php
 */
if ( PHP_SAPI !== 'cli' ) {
	exit;
}
require dirname( __DIR__, 4 ) . '/wp-load.php';

$pass = 0;
$fail = 0;
function check( $label, $cond ) {
	global $pass, $fail;
	$cond ? $pass++ : $fail++;
	echo ( $cond ? '  ✔ ' : '  ✘ ' ) . $label . "\n";
}
function count_items( $html ) {
	return substr_count( $html, 'class="salon-services__item"' );
}

$total = (int) wp_count_posts( 'salon_service' )->publish;

echo "== 1. Shortcode [salon_services] ==\n";
check( 'Đã đăng ký', shortcode_exists( 'salon_services' ) );

$html = do_shortcode( '[salon_services]' );
check( "Mặc định limit=6 → 6 dịch vụ (có {$total})", min( 6, $total ) === count_items( $html ) );
check( 'Mặc định có giá', false !== strpos( $html, 'salon-services__meta' ) );

$html = do_shortcode( '[salon_services limit="2" category="mong-tay" show_price="no"]' );
check( 'limit=2 category=mong-tay → 2 dịch vụ', 2 === count_items( $html ) );
check( 'show_price="no" → KHÔNG có giá (bẫy (bool)"no" === true)', false === strpos( $html, 'salon-services__meta' ) );

$html = do_shortcode( '[salon_services category="mong-chan"]' );
check( 'category=mong-chan → chỉ Pedicure', 1 === count_items( $html ) && false !== strpos( $html, 'Chăm sóc móng chân' ) );

$html = do_shortcode( '[salon_services limit="99999" columns="abc"]' );
check( 'limit=99999 bị chặn ≤ 12; columns="abc" → 1 cột', count_items( $html ) <= 12 && false !== strpos( $html, 'salon-services--cols-1' ) );

$html = do_shortcode( '[salon_services category="<script>alert(1)</script>"]' );
check( 'category chứa <script> → sanitize_title, không có thẻ script, báo rỗng', false === stripos( $html, '<script' ) && false !== strpos( $html, 'salon-services__empty' ) );

$html = do_shortcode( '[salon_services bogus="1" limit="1"]' );
check( 'Thuộc tính lạ bị shortcode_atts bỏ qua', 1 === count_items( $html ) );

$override = static function ( $out ) {
	$out['limit'] = 1;
	return $out;
};
add_filter( 'shortcode_atts_salon_services', $override );
check( 'Filter shortcode_atts_salon_services đổi được limit', 1 === count_items( do_shortcode( '[salon_services limit="5"]' ) ) );
remove_filter( 'shortcode_atts_salon_services', $override );

check( 'strip_shortcodes() xoá shortcode (Core dùng khi tự tạo excerpt)', '' === trim( strip_shortcodes( '[salon_services]' ) ) );

echo "== 2. Block salon/featured-services ==\n";
$type = WP_Block_Type_Registry::get_instance()->get_registered( 'salon/featured-services' );
check( 'Đã đăng ký từ block.json', $type instanceof WP_Block_Type );
check( 'Là dynamic block (có render_callback)', $type && $type->is_dynamic() );
check( 'editorScript handle có wp-server-side-render trong deps', $type && in_array( 'wp-server-side-render', wp_scripts()->registered[ $type->editor_script_handles[0] ]->deps, true ) );
check( 'Style handle = salon-frontend', $type && in_array( 'salon-frontend', $type->style_handles, true ) );

$html = do_blocks( '<!-- wp:salon/featured-services {"count":2,"category":"mong-tay"} /-->' );
check( 'count=2 → 2 dịch vụ', 2 === count_items( $html ) );
check( 'Wrapper có class wp-block-salon-featured-services', false !== strpos( $html, 'wp-block-salon-featured-services' ) );

$html = do_blocks( '<!-- wp:salon/featured-services {"count":"abc"} /-->' );
check( 'count="abc" (sai kiểu so với block.json) → Core dùng default 3', 3 === count_items( $html ) );

$html = do_blocks( '<!-- wp:salon/featured-services {"align":"wide","backgroundColor":"pale-pink"} /-->' );
check( 'supports: align/color → class alignwide + has-background', false !== strpos( $html, 'alignwide' ) && false !== strpos( $html, 'has-background' ) );

$page = get_page_by_path( 'uu-dai' );
check( 'Trang /uu-dai/: post_content chỉ lưu comment, không lưu HTML', $page && false === strpos( $page->post_content, 'salon-services__item' ) );
$blocks = parse_blocks( $page->post_content );
$ours   = wp_list_filter( $blocks, array( 'blockName' => 'salon/featured-services' ) );
check( 'parse_blocks thấy 2 block salon/featured-services', 2 === count( $ours ) );
check( 'Attribute đọc từ JSON trong comment: category = mong-tay', 'mong-tay' === reset( $ours )['attrs']['category'] );

echo "== 3. Widget & sidebar ==\n";
global $wp_widget_factory, $wp_registered_sidebars;
check( 'Widget Salon_Hours_Widget đã register_widget', isset( $wp_widget_factory->widgets['Salon_Hours_Widget'] ) );
check( 'Theme đã register_sidebar sidebar-service', isset( $wp_registered_sidebars['sidebar-service'] ) );
check( 'Sidebar có widget (is_active_sidebar)', is_active_sidebar( 'sidebar-service' ) );

ob_start();
the_widget( 'Salon_Hours_Widget', array( 'title' => 'Giờ <b>mở</b> cửa', 'show_button' => false ) );
$html = ob_get_clean();
$open = salon_get_setting( 'open_time' ) . ' – ' . salon_get_setting( 'close_time' );
check( "Widget in giờ từ settings ({$open})", false !== strpos( $html, $open ) );
check( 'Tiêu đề được escape (không còn thẻ <b>)', false === strpos( $html, '<b>' ) );
check( 'show_button=false → không có nút', false === strpos( $html, 'salon-hours__button' ) );

$widget  = $wp_widget_factory->widgets['Salon_Hours_Widget'];
$updated = $widget->update( array( 'title' => '<script>x</script>Giờ', 'show_button' => '' ), array() );
check( 'update(): title bị sanitize, checkbox rỗng → false', 'Giờ' === $updated['title'] && false === $updated['show_button'] );

echo "\nKết quả: {$pass} đạt, {$fail} lỗi\n";
exit( $fail ? 1 : 0 );
