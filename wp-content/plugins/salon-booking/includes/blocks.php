<?php
/**
 * Đăng ký block của plugin từ block.json.
 *
 * register_block_type( thư mục ) → đọc block.json → đăng ký editorScript (kèm index.asset.php),
 * style, render.php, attributes. Nhờ đăng ký ở PHP: REST, ServerSideRender, widget block, và
 * frontend đều biết block này — kể cả khi editor chưa nạp JS.
 *
 * @package SalonBooking
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'salon_register_blocks' );

function salon_register_blocks() {
	register_block_type( plugin_dir_path( SALON_FILE ) . 'blocks/featured-services' );
}
