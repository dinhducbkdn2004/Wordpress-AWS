<?php
/**
 * Plugin Name: Lifecycle Tracer (học tập)
 * Description: Ghi thứ tự các hook chính của mỗi request vào wp-content/debug.log.
 */
defined( 'ABSPATH' ) || exit; // chặn truy cập file trực tiếp qua URL

if ( wp_doing_cron() || wp_doing_ajax() ) {
	return; // bỏ qua request nền cho log đỡ nhiễu
}

function lt_log( $label ) {
	error_log( sprintf( '[%7.1f ms] %-24s %s', timer_float() * 1000, $label, $_SERVER['REQUEST_URI'] ?? '' ) );
}

$hooks = array( 'muplugins_loaded', 'plugins_loaded', 'setup_theme', 'after_setup_theme',
	'init', 'wp_loaded', 'parse_request', 'wp', 'template_redirect', 'wp_head', 'shutdown' );

foreach ( $hooks as $hook ) {
	add_action( $hook, function () use ( $hook ) { lt_log( $hook ); } );
}

add_filter( 'template_include', function ( $template ) {
	lt_log( 'TEMPLATE: ' . str_replace( ABSPATH, '', $template ) );
	return $template; // filter BẮT BUỘC phải trả lại giá trị, nếu không trang sẽ trắng
} );
