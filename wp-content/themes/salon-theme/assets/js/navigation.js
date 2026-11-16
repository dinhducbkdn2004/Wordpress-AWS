/**
 * Bật/tắt menu trên mobile. Không cần jQuery — trình duyệt hiện đại đủ API.
 * Nạp với strategy "defer" → chạy sau khi HTML parse xong, DOM đã có nút.
 */
( function () {
	const toggle = document.querySelector( '.menu-toggle' );
	const nav = document.getElementById( 'site-navigation' );

	if ( ! toggle || ! nav ) {
		return;
	}

	function setOpen( open ) {
		toggle.setAttribute( 'aria-expanded', String( open ) ); // cho trình đọc màn hình biết trạng thái
		nav.classList.toggle( 'is-open', open );
		document.body.classList.toggle( 'menu-open', open );
	}

	toggle.addEventListener( 'click', function () {
		setOpen( toggle.getAttribute( 'aria-expanded' ) !== 'true' );
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( event.key === 'Escape' && nav.classList.contains( 'is-open' ) ) {
			setOpen( false );
			toggle.focus();
		}
	} );
} )();
