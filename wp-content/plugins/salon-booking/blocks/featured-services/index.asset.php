<?php
/**
 * Khai báo phụ thuộc của index.js. register_block_type() đọc file này (blocks.php: register_block_script_handle)
 * để biết phải nạp wp-blocks, wp-element... TRƯỚC index.js.
 *
 * Khi dùng @wordpress/scripts (npm run build), file này được SINH TỰ ĐỘNG từ các import.
 * Ở đây viết tay vì bài học không dùng bước build.
 *
 * @package SalonBooking
 */

return array(
	'dependencies' => array(
		'wp-blocks',             // registerBlockType
		'wp-element',            // createElement (React)
		'wp-block-editor',       // useBlockProps, InspectorControls
		'wp-components',         // PanelBody, RangeControl, SelectControl, ToggleControl
		'wp-data',               // useSelect
		'wp-core-data',          // store 'core' → getEntityRecords (lấy danh sách loại dịch vụ qua REST)
		'wp-server-side-render', // xem trước bằng HTML do PHP render
	),
	'version'      => (string) filemtime( __DIR__ . '/index.js' ),
);
