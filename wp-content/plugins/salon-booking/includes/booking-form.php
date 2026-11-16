<?php
/**
 * Form đặt lịch: shortcode [salon_booking_form] + xử lý submit qua admin-post.php.
 *
 * Luồng PRG (Post → Redirect → Get):
 *   GET  /dat-lich/                         → hiện form (kèm nonce)
 *   POST /wp-admin/admin-post.php           action=salon_book
 *        → kiểm tra nonce → honeypot → validate → lưu
 *        → cất kết quả (lỗi + dữ liệu đã nhập, HOẶC thông tin lịch) vào transient 10 phút
 *        → 303 redirect về /dat-lich/?salon_form=<uuid>
 *   GET  /dat-lich/?salon_form=<uuid>        → đọc transient 1 lần → hiện lỗi / thông báo thành công
 * Nhờ redirect, F5 sau khi đặt KHÔNG gửi lại form (không tạo lịch trùng).
 *
 * @package SalonBooking
 */

defined( 'ABSPATH' ) || exit;

const SALON_FORM_ACTION = 'salon_book'; // dùng cho cả input "action" lẫn nonce action

add_action( 'init', 'salon_register_booking_form_shortcode' );

/**
 * Đăng ký shortcode.
 */
function salon_register_booking_form_shortcode() {
	add_shortcode( 'salon_booking_form', 'salon_shortcode_booking_form' );
}

// admin-post.php gọi hook khác nhau tuỳ đã đăng nhập hay chưa → đăng ký CẢ HAI.
// Thiếu bản nopriv → khách vãng lai gửi form nhận trang trắng 400 (wp-admin/admin-post.php).
add_action( 'admin_post_nopriv_' . SALON_FORM_ACTION, 'salon_handle_booking_form' );
add_action( 'admin_post_' . SALON_FORM_ACTION, 'salon_handle_booking_form' );

/* -------------------------------------------------------------------------
 * XỬ LÝ SUBMIT
 * ---------------------------------------------------------------------- */

/**
 * Nhận POST từ form. Luôn kết thúc bằng redirect (không bao giờ in HTML).
 */
function salon_handle_booking_form() {
	$back = salon_booking_form_back_url();

	// 1. Chỉ nhận POST: một đường link GET (vd <img src="admin-post.php?action=salon_book">) không được làm gì.
	if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
		wp_safe_redirect( $back );
		exit;
	}

	// 2. Nonce: chống CSRF (trang web khác tự submit form vào site mình).
	//    Form public → dùng wp_verify_nonce() rồi tự báo lỗi thân thiện,
	//    KHÔNG dùng check_admin_referer() (nó wp_die() trang "The link you followed has expired").
	$nonce = isset( $_POST['salon_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['salon_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, SALON_FORM_ACTION ) ) {
		salon_booking_form_redirect(
			$back,
			array( 'errors' => array( 'form' => array( 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang rồi gửi lại.' ) ) )
		);
	}

	// 3. Honeypot: ô ẩn bằng CSS — người thật để trống, bot tự điền mọi ô.
	if ( ! empty( $_POST['salon_website'] ) ) {
		salon_booking_form_redirect( $back, array( 'errors' => array( 'form' => array( 'Không gửi được yêu cầu. Vui lòng thử lại.' ) ) ) );
	}

	// 4. Lấy dữ liệu: WP đã tự thêm "\" vào $_POST (wp_magic_quotes, wp-settings.php) → wp_unslash.
	//    Sanitize/validate từng field nằm trong salon_validate_booking_input().
	$raw = ( isset( $_POST['salon_booking'] ) && is_array( $_POST['salon_booking'] ) )
		? wp_unslash( $_POST['salon_booking'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validate ở bước 5
		: array();

	// 5. Validate + sanitize.
	$result = salon_validate_booking_input( $raw );

	// 6. Lưu. salon_create_booking() kiểm tra trùng lịch LẦN NỮA ngay trước khi ghi
	//    (giữa bước 5 và 6 có thể có người khác vừa đặt cùng giờ).
	if ( ! is_wp_error( $result ) ) {
		$booking_id = salon_create_booking( $result );

		if ( is_wp_error( $booking_id ) ) {
			$result = 'salon_slot_taken' === $booking_id->get_error_code()
				? new WP_Error( 'time', 'Khung giờ này vừa có người đặt, vui lòng chọn giờ khác.' )
				: new WP_Error( 'form', 'Hệ thống đang bận, vui lòng thử lại sau ít phút.' );
		}
	}

	if ( is_wp_error( $result ) ) {
		salon_booking_form_redirect(
			$back,
			array(
				'errors' => salon_wp_error_by_field( $result ),
				'old'    => salon_booking_old_input( $raw ),
			)
		);
	}

	// 7. Thành công: chỉ cất những gì cần để hiển thị.
	salon_booking_form_redirect(
		$back,
		array(
			'success' => array(
				'id'         => $booking_id,
				'service_id' => $result['service_id'],
				'staff_id'   => $result['staff_id'],
				'start_at'   => $result['start_at'],
				'phone'      => $result['phone'],
			),
		)
	);
}

/**
 * Trang để quay về sau khi xử lý: trang chứa form (lấy từ _wp_http_referer do wp_nonce_field() in ra).
 * wp_get_referer() đã qua wp_validate_redirect() → chỉ chấp nhận cùng domain (chống open redirect).
 *
 * @return string
 */
function salon_booking_form_back_url() {
	$url = wp_get_referer();
	if ( ! $url ) {
		$url = salon_get_booking_url();
	}
	if ( ! $url ) {
		$url = home_url( '/' );
	}
	return remove_query_arg( 'salon_form', $url );
}

/**
 * Cất dữ liệu "flash" vào transient rồi redirect 303 về form. Hàm này KHÔNG trả về.
 *
 * @param string $url     URL trang form.
 * @param array  $payload errors/old hoặc success.
 */
function salon_booking_form_redirect( $url, array $payload ) {
	$key = wp_generate_uuid4(); // ngẫu nhiên, không đoán được → người khác không xem được dữ liệu của khách
	set_transient( 'salon_form_' . $key, $payload, 10 * MINUTE_IN_SECONDS );

	// 303 See Other: trình duyệt chuyển sang GET → F5 không gửi lại POST.
	wp_safe_redirect( add_query_arg( 'salon_form', $key, $url ) . '#salon-booking', 303 );
	exit;
}

/**
 * WP_Error (nhiều lỗi, code = tên field) → [ field => [ message, ... ] ].
 *
 * @param WP_Error $error Lỗi.
 * @return array<string,string[]>
 */
function salon_wp_error_by_field( WP_Error $error ) {
	$out = array();
	foreach ( $error->get_error_codes() as $code ) {
		$out[ $code ] = $error->get_error_messages( $code );
	}
	return $out;
}

/**
 * Dữ liệu khách đã nhập, để điền lại form khi có lỗi.
 * Cố ý KHÔNG sanitize: trả lại đúng những gì khách gõ để họ sửa. An toàn nhờ ESCAPE khi in ra.
 *
 * @param array $raw Dữ liệu thô.
 * @return array<string,string>
 */
function salon_booking_old_input( array $raw ) {
	$old = array();
	foreach ( salon_booking_input_keys() as $key ) {
		$value       = ( isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ) ? (string) $raw[ $key ] : '';
		$old[ $key ] = mb_substr( $value, 0, 1000 ); // giới hạn kích thước transient
	}
	return $old;
}

/**
 * Đọc flash từ ?salon_form=<uuid> — 1 lần, rồi xoá.
 * Cache trong static: the_content có thể chạy nhiều lần/1 request (plugin SEO tạo description,
 * excerpt...) → lần đọc thứ 2 vẫn có dữ liệu dù transient đã xoá.
 *
 * @return array
 */
function salon_booking_form_flash() {
	static $cache = array();

	// Chỉ đọc, không đổi dữ liệu → không cần nonce. Key phải đúng định dạng UUID v4.
	$key = isset( $_GET['salon_form'] ) ? sanitize_text_field( wp_unslash( $_GET['salon_form'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( '' === $key || ! wp_is_uuid( $key, 4 ) ) {
		return array();
	}

	if ( ! array_key_exists( $key, $cache ) ) {
		$cache[ $key ] = get_transient( 'salon_form_' . $key );
		delete_transient( 'salon_form_' . $key );
	}
	return is_array( $cache[ $key ] ) ? $cache[ $key ] : array();
}

/* -------------------------------------------------------------------------
 * HIỂN THỊ
 * ---------------------------------------------------------------------- */

/**
 * Callback shortcode [salon_booking_form]. Shortcode phải RETURN chuỗi.
 *
 * @return string
 */
function salon_shortcode_booking_form() {
	wp_enqueue_style( 'salon-frontend' );

	$flash = salon_booking_form_flash();
	if ( ! empty( $flash['success'] ) && is_array( $flash['success'] ) ) {
		return salon_render_booking_success( $flash['success'] );
	}

	return salon_render_booking_form(
		isset( $flash['errors'] ) && is_array( $flash['errors'] ) ? $flash['errors'] : array(),
		isset( $flash['old'] ) && is_array( $flash['old'] ) ? $flash['old'] : array()
	);
}

/**
 * Nhãn field (dùng cho label và danh sách lỗi tổng hợp).
 *
 * @return array<string,string>
 */
function salon_booking_field_labels() {
	return array(
		'service_id'    => 'Dịch vụ',
		'staff_id'      => 'Nhân viên',
		'date'          => 'Ngày',
		'time'          => 'Giờ',
		'customer_name' => 'Họ tên',
		'phone'         => 'Số điện thoại',
		'email'         => 'Email',
		'note'          => 'Ghi chú',
	);
}

/**
 * In id/name + thuộc tính a11y khi field có lỗi (trình đọc màn hình đọc được lỗi gắn với ô).
 *
 * @param string $key    Field key.
 * @param array  $errors Lỗi theo field.
 */
function salon_form_field_attrs( $key, array $errors ) {
	printf( 'id="%1$s" name="%2$s"', esc_attr( 'salon-' . $key ), esc_attr( 'salon_booking[' . $key . ']' ) );
	if ( ! empty( $errors[ $key ] ) ) {
		printf( ' aria-invalid="true" aria-describedby="%s"', esc_attr( 'salon-' . $key . '-error' ) );
	}
}

/**
 * In thông báo lỗi dưới 1 field.
 *
 * @param string $key    Field key.
 * @param array  $errors Lỗi theo field.
 */
function salon_form_field_error( $key, array $errors ) {
	if ( empty( $errors[ $key ] ) ) {
		return;
	}
	printf(
		'<p id="%1$s" class="salon-form__error">%2$s</p>',
		esc_attr( 'salon-' . $key . '-error' ),
		esc_html( implode( ' ', (array) $errors[ $key ] ) )
	);
}

/**
 * HTML form đặt lịch.
 *
 * @param array $errors [ field => string[] ], field đặc biệt 'form' = lỗi chung.
 * @param array $old    Dữ liệu khách đã nhập (chưa escape!).
 * @return string
 */
function salon_render_booking_form( array $errors, array $old ) {
	$labels = salon_booking_field_labels();
	$limits = salon_booking_limits();
	$now    = current_datetime();
	$slots  = salon_get_time_slots();
	$user   = wp_get_current_user();

	$query = array(
		'post_status' => 'publish',
		'numberposts' => -1,
		'orderby'     => array(
			'menu_order' => 'ASC',
			'title'      => 'ASC',
		),
	);
	$services = get_posts( array( 'post_type' => 'salon_service' ) + $query );
	$staff    = get_posts( array( 'post_type' => 'salon_staff' ) + $query );

	// Giá trị ban đầu: dữ liệu cũ (sau lỗi) > ?service= từ nút "Đặt lịch" > thông tin user đăng nhập.
	$values = wp_parse_args(
		$old,
		array(
			'service_id'    => isset( $_GET['service'] ) ? (string) absint( $_GET['service'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'staff_id'      => '0',
			'date'          => '',
			'time'          => '',
			'customer_name' => $user->exists() ? $user->display_name : '',
			'phone'         => '',
			'email'         => $user->exists() ? $user->user_email : '',
			'note'          => '',
		)
	);

	ob_start();
	?>
	<div id="salon-booking" class="salon-form-wrap">
		<?php if ( $errors ) : ?>
			<div class="salon-form__notice salon-form__notice--error" role="alert">
				<?php foreach ( isset( $errors['form'] ) ? (array) $errors['form'] : array() as $message ) : ?>
					<p><strong><?php echo esc_html( $message ); ?></strong></p>
				<?php endforeach; ?>

				<?php $field_errors = array_intersect_key( $errors, $labels ); ?>
				<?php if ( $field_errors ) : ?>
					<p>Vui lòng kiểm tra lại <?php echo esc_html( count( $field_errors ) ); ?> mục:</p>
					<ul>
						<?php foreach ( $field_errors as $key => $messages ) : ?>
							<li><a href="<?php echo esc_attr( '#salon-' . $key ); ?>"><?php echo esc_html( $labels[ $key ] . ': ' . implode( ' ', (array) $messages ) ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<form class="salon-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( SALON_FORM_ACTION ); ?>">
			<?php wp_nonce_field( SALON_FORM_ACTION, 'salon_nonce' ); // in 2 input: salon_nonce + _wp_http_referer ?>

			<fieldset class="salon-form__group">
				<legend>1. Dịch vụ</legend>

				<p class="salon-form__field">
					<label for="salon-service_id"><?php echo esc_html( $labels['service_id'] ); ?> <span class="salon-form__req" aria-hidden="true">*</span></label>
					<select <?php salon_form_field_attrs( 'service_id', $errors ); ?> required>
						<option value="">— Chọn dịch vụ —</option>
						<?php foreach ( $services as $service ) : ?>
							<?php $details = salon_get_service_details( $service->ID ); ?>
							<option value="<?php echo esc_attr( $service->ID ); ?>" <?php selected( $values['service_id'], (string) $service->ID ); ?>>
								<?php echo esc_html( sprintf( '%1$s — %2$s · %3$d phút', $service->post_title, salon_format_price( $details['price'] ), $details['duration'] ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php salon_form_field_error( 'service_id', $errors ); ?>
				</p>

				<p class="salon-form__field">
					<label for="salon-staff_id"><?php echo esc_html( $labels['staff_id'] ); ?></label>
					<select <?php salon_form_field_attrs( 'staff_id', $errors ); ?>>
						<option value="0">Ai rảnh cũng được</option>
						<?php foreach ( $staff as $member ) : ?>
							<option value="<?php echo esc_attr( $member->ID ); ?>" <?php selected( $values['staff_id'], (string) $member->ID ); ?>><?php echo esc_html( $member->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php salon_form_field_error( 'staff_id', $errors ); ?>
				</p>
			</fieldset>

			<fieldset class="salon-form__group">
				<legend>2. Thời gian</legend>
				<div class="salon-form__row">
					<p class="salon-form__field">
						<label for="salon-date"><?php echo esc_html( $labels['date'] ); ?> <span class="salon-form__req" aria-hidden="true">*</span></label>
						<input type="date" <?php salon_form_field_attrs( 'date', $errors ); ?> value="<?php echo esc_attr( $values['date'] ); ?>"
							min="<?php echo esc_attr( $now->format( 'Y-m-d' ) ); ?>"
							max="<?php echo esc_attr( $now->modify( '+' . (int) $limits['max_days_ahead'] . ' days' )->format( 'Y-m-d' ) ); ?>" required>
						<?php salon_form_field_error( 'date', $errors ); ?>
					</p>

					<p class="salon-form__field">
						<label for="salon-time"><?php echo esc_html( $labels['time'] ); ?> <span class="salon-form__req" aria-hidden="true">*</span></label>
						<select <?php salon_form_field_attrs( 'time', $errors ); ?> required>
							<option value="">— Chọn giờ —</option>
							<?php foreach ( $slots as $slot ) : ?>
								<option value="<?php echo esc_attr( $slot ); ?>" <?php selected( $values['time'], $slot ); ?>><?php echo esc_html( $slot ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php salon_form_field_error( 'time', $errors ); ?>
					</p>
				</div>
				<p class="salon-form__hint">
					<?php
					echo esc_html(
						sprintf(
							'Salon mở cửa %1$s – %2$s. Lịch trống được kiểm tra khi bạn gửi form.',
							salon_get_setting( 'open_time' ),
							salon_get_setting( 'close_time' )
						)
					);
					?>
				</p>
			</fieldset>

			<fieldset class="salon-form__group">
				<legend>3. Thông tin liên hệ</legend>

				<p class="salon-form__field">
					<label for="salon-customer_name"><?php echo esc_html( $labels['customer_name'] ); ?> <span class="salon-form__req" aria-hidden="true">*</span></label>
					<input type="text" <?php salon_form_field_attrs( 'customer_name', $errors ); ?> value="<?php echo esc_attr( $values['customer_name'] ); ?>"
						maxlength="<?php echo esc_attr( $limits['name_max'] ); ?>" autocomplete="name" required>
					<?php salon_form_field_error( 'customer_name', $errors ); ?>
				</p>

				<div class="salon-form__row">
					<p class="salon-form__field">
						<label for="salon-phone"><?php echo esc_html( $labels['phone'] ); ?> <span class="salon-form__req" aria-hidden="true">*</span></label>
						<input type="tel" <?php salon_form_field_attrs( 'phone', $errors ); ?> value="<?php echo esc_attr( $values['phone'] ); ?>"
							placeholder="0912 345 678" maxlength="20" inputmode="tel" autocomplete="tel" required>
						<?php salon_form_field_error( 'phone', $errors ); ?>
					</p>

					<p class="salon-form__field">
						<label for="salon-email"><?php echo esc_html( $labels['email'] ); ?> <span class="salon-form__optional">(không bắt buộc)</span></label>
						<input type="email" <?php salon_form_field_attrs( 'email', $errors ); ?> value="<?php echo esc_attr( $values['email'] ); ?>"
							maxlength="<?php echo esc_attr( $limits['email_max'] ); ?>" autocomplete="email">
						<?php salon_form_field_error( 'email', $errors ); ?>
					</p>
				</div>

				<p class="salon-form__field">
					<label for="salon-note"><?php echo esc_html( $labels['note'] ); ?></label>
					<textarea <?php salon_form_field_attrs( 'note', $errors ); ?> rows="3" maxlength="<?php echo esc_attr( $limits['note_max'] ); ?>"><?php echo esc_textarea( $values['note'] ); ?></textarea>
					<?php salon_form_field_error( 'note', $errors ); ?>
				</p>
			</fieldset>

			<?php // Honeypot: ẩn bằng CSS (không dùng type="hidden" — bot bỏ qua ô hidden). ?>
			<div class="salon-form__hp" aria-hidden="true">
				<label for="salon-website">Website</label>
				<input type="text" id="salon-website" name="salon_website" value="" tabindex="-1" autocomplete="off">
			</div>

			<p class="salon-form__actions">
				<button type="submit" class="salon-form__submit">Gửi yêu cầu đặt lịch</button>
			</p>
		</form>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Thông báo đặt lịch thành công.
 *
 * @param array $success id, service_id, staff_id, start_at (UTC), phone.
 * @return string
 */
function salon_render_booking_success( array $success ) {
	$timestamp = strtotime( $success['start_at'] . ' UTC' );
	$again_url = salon_get_booking_url();

	ob_start();
	?>
	<div id="salon-booking" class="salon-form__notice salon-form__notice--success" role="status">
		<h2 class="salon-form__title">Đã nhận yêu cầu đặt lịch!</h2>
		<p>Mã lịch hẹn: <strong>#<?php echo esc_html( $success['id'] ); ?></strong></p>
		<ul>
			<li>Dịch vụ: <strong><?php echo esc_html( get_the_title( $success['service_id'] ) ); ?></strong></li>
			<li>Nhân viên: <strong><?php echo esc_html( get_the_title( $success['staff_id'] ) ); ?></strong></li>
			<li>Thời gian: <strong><?php echo esc_html( wp_date( 'H:i', $timestamp ) . ' ngày ' . wp_date( 'd/m/Y', $timestamp ) ); ?></strong></li>
		</ul>
		<p>Salon sẽ gọi số <strong><?php echo esc_html( $success['phone'] ); ?></strong> để xác nhận lịch hẹn.</p>
		<?php if ( $again_url ) : ?>
			<p><a href="<?php echo esc_url( $again_url ); ?>">Đặt thêm lịch khác</a></p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
