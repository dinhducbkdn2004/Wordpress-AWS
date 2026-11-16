/**
 * Block "Dịch vụ nổi bật" — phần chạy trong EDITOR (không chạy ở frontend).
 *
 * Viết bằng JS thuần + global window.wp để không cần bước build.
 * Dự án thật thường viết JSX + import từ '@wordpress/...' rồi build bằng @wordpress/scripts;
 * hai cách cho ra cùng một kết quả: `el( PanelBody, { title } )` chính là `<PanelBody title={...} />`.
 *
 * Đây là DYNAMIC block: save() trả về null → trong post_content chỉ lưu comment
 *   <!-- wp:salon/featured-services {"count":4,"category":"mong-tay"} /-->
 * còn HTML do render.php sinh ra ở MỖI request → đổi giá dịch vụ là block cập nhật ngay.
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { useBlockProps, InspectorControls } = wp.blockEditor;
	const { PanelBody, RangeControl, SelectControl, ToggleControl } = wp.components;
	const { useSelect } = wp.data;
	const ServerSideRender = wp.serverSideRender;

	// Thông tin chung (title, icon, attributes...) lấy từ block.json — đã được PHP đăng ký trước.
	registerBlockType( 'salon/featured-services', {
		edit: function Edit( { attributes, setAttributes } ) {
			const blockProps = useBlockProps(); // class, style, data-* mà editor cần trên thẻ bọc ngoài

			// Lấy loại dịch vụ qua REST (/wp/v2/salon_service_cat) — cần taxonomy show_in_rest (Phần 5).
			// Lần render đầu trả về null (đang tải), useSelect tự render lại khi có dữ liệu.
			const terms = useSelect(
				( select ) =>
					select( 'core' ).getEntityRecords( 'taxonomy', 'salon_service_cat', {
						per_page: -1,
						hide_empty: true,
					} ),
				[]
			);

			const categoryOptions = [ { label: 'Tất cả loại', value: '' } ].concat(
				( terms || [] ).map( ( term ) => ( { label: term.name, value: term.slug } ) )
			);

			return el(
				Fragment,
				null,
				// Thanh bên phải (Block settings).
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: 'Hiển thị' },
						el( RangeControl, {
							label: 'Số dịch vụ',
							value: attributes.count,
							min: 1,
							max: 12,
							onChange: ( value ) => setAttributes( { count: value } ),
							__next40pxDefaultSize: true,
							__nextHasNoMarginBottom: true,
						} ),
						el( RangeControl, {
							label: 'Số cột',
							value: attributes.columns,
							min: 1,
							max: 4,
							onChange: ( value ) => setAttributes( { columns: value } ),
							__next40pxDefaultSize: true,
							__nextHasNoMarginBottom: true,
						} ),
						el( SelectControl, {
							label: 'Loại dịch vụ',
							value: attributes.category,
							options: categoryOptions,
							onChange: ( value ) => setAttributes( { category: value } ),
							__next40pxDefaultSize: true,
							__nextHasNoMarginBottom: true,
						} ),
						el( ToggleControl, {
							label: 'Hiện giá & thời lượng',
							checked: attributes.showPrice,
							onChange: ( value ) => setAttributes( { showPrice: value } ),
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				// Nội dung trong khung soạn thảo: gọi REST /wp/v2/block-renderer/salon/featured-services
				// → PHP chạy render.php → trả HTML. Editor và frontend dùng CHUNG một code PHP.
				el(
					'div',
					blockProps,
					el( ServerSideRender, {
						block: 'salon/featured-services',
						attributes,
						skipBlockSupportAttributes: true, // màu/spacing đã nằm trên blockProps, không áp lần 2
					} )
				)
			);
		},

		save: () => null, // dynamic block: không lưu HTML
	} );
} )( window.wp );
