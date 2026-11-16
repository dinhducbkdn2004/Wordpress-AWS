<?php
/**
 * Dữ liệu demo Phần 6:
 *  - Trang /bang-gia/ dùng SHORTCODE [salon_services]
 *  - Trang /uu-dai/  dùng BLOCK salon/featured-services
 *  - Sidebar "sidebar-service": widget cổ điển "Giờ mở cửa" + 1 block widget chứa block của mình
 * Chạy lại nhiều lần không bị trùng.
 *
 * Chạy (đứng ở C:\xampp\htdocs\wordpress):
 *   C:\xampp\php\php.exe wp-content\plugins\salon-booking\dev\seed-part6.php
 */
if ( PHP_SAPI !== 'cli' ) {
	exit;
}
require dirname( __DIR__, 4 ) . '/wp-load.php';

/** Tạo trang nếu slug chưa có, ngược lại cập nhật nội dung. */
function seed6_page( $slug, $title, $content ) {
	$existing = get_page_by_path( $slug, OBJECT, 'page' );
	$postarr  = array(
		'post_type'    => 'page',
		'post_name'    => $slug,
		'post_title'   => $title,
		'post_status'  => 'publish',
		'post_content' => $content,
	);
	if ( $existing ) {
		$postarr['ID'] = $existing->ID;
	}
	$id = $existing ? wp_update_post( $postarr, true ) : wp_insert_post( $postarr, true );
	echo is_wp_error( $id ) ? "  ✘ {$slug}: " . $id->get_error_message() . "\n" : "  ✔ page #{$id} /{$slug}/\n";
	return $id;
}

echo "== Trang ==\n";
// Shortcode đặt trong block "Shortcode" (core/shortcode) — cách Block Editor chứa shortcode.
seed6_page(
	'bang-gia',
	'Bảng giá',
	"<!-- wp:paragraph -->\n<p>Giá đã bao gồm tư vấn và dụng cụ dùng một lần.</p>\n<!-- /wp:paragraph -->\n\n"
	. "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Móng tay</h2>\n<!-- /wp:heading -->\n\n"
	. "<!-- wp:shortcode -->\n[salon_services category=\"mong-tay\" columns=\"2\" limit=\"12\"]\n<!-- /wp:shortcode -->\n\n"
	. "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Dịch vụ khác</h2>\n<!-- /wp:heading -->\n\n"
	. "<!-- wp:shortcode -->\n[salon_services category=\"mong-chan\" columns=\"2\"]\n<!-- /wp:shortcode -->\n\n"
	. "<!-- wp:shortcode -->\n[salon_services category=\"cham-soc-toc\" columns=\"2\"]\n<!-- /wp:shortcode -->"
);

// Block động: trong DB chỉ có comment + JSON attribute, KHÔNG có HTML.
seed6_page(
	'uu-dai',
	'Ưu đãi tháng 11',
	"<!-- wp:paragraph -->\n<p>Giảm 10% cho các dịch vụ móng tay khi đặt lịch online.</p>\n<!-- /wp:paragraph -->\n\n"
	. "<!-- wp:salon/featured-services {\"count\":4,\"columns\":2,\"category\":\"mong-tay\"} /-->\n\n"
	. "<!-- wp:paragraph -->\n<p>Tất cả dịch vụ (không hiện giá):</p>\n<!-- /wp:paragraph -->\n\n"
	. "<!-- wp:salon/featured-services {\"count\":6,\"showPrice\":false,\"align\":\"wide\"} /-->"
);

echo "== Widget ==\n";
$sidebars = wp_get_sidebars_widgets(); // option sidebars_widgets: [ sidebar_id => [ widget_id, ... ] ]

if ( ! empty( $sidebars['sidebar-service'] ) ) {
	echo '  • sidebar-service đã có widget: ' . implode( ', ', $sidebars['sidebar-service'] ) . " → bỏ qua\n";
	exit;
}

// 1) Widget cổ điển: instance lưu ở option widget_{id_base}, key là số thứ tự.
$hours            = get_option( 'widget_salon_hours', array() );
$hours_number     = max( array_merge( array( 1 ), array_filter( array_keys( $hours ), 'is_int' ) ) ) + 1; // WP bắt đầu từ 2; option có thể chỉ có key "_multiwidget"
$hours[ $hours_number ] = array(
	'title'       => 'Giờ mở cửa',
	'show_button' => true,
);
$hours['_multiwidget'] = 1; // đánh dấu định dạng "nhiều bản sao" (WP_Widget luôn dùng)
update_option( 'widget_salon_hours', $hours );

// 2) Block widget (từ WP 5.8): instance chỉ là 1 chuỗi block markup, lưu ở option widget_block.
$blocks       = get_option( 'widget_block', array() );
$block_number = max( array_merge( array( 1 ), array_filter( array_keys( $blocks ), 'is_int' ) ) ) + 1;
$blocks[ $block_number ] = array(
	'content' => "<!-- wp:heading {\"level\":2} -->\n<h2 class=\"wp-block-heading\">Có thể bạn thích</h2>\n<!-- /wp:heading -->\n\n"
		. '<!-- wp:salon/featured-services {"count":2,"columns":1} /-->',
);
$blocks['_multiwidget'] = 1;
update_option( 'widget_block', $blocks );

$sidebars['sidebar-service'] = array( "salon_hours-{$hours_number}", "block-{$block_number}" );
wp_set_sidebars_widgets( $sidebars );

echo "  ✔ sidebar-service: salon_hours-{$hours_number}, block-{$block_number}\n";
