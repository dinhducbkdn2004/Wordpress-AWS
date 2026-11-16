<?php
/**
 * Widget kiểu cổ điển (WP_Widget): "Salon: Giờ mở cửa".
 *
 * Từ WP 5.8, màn hình Appearance → Widgets là block editor; widget cổ điển vẫn dùng được
 * thông qua block "Legacy Widget". Dữ liệu từng widget lưu ở option "widget_salon_hours"
 * (mảng theo số thứ tự: salon_hours-2 → [2 => instance]); widget nằm ở sidebar nào lưu ở "sidebars_widgets".
 *
 * @package SalonBooking
 */

defined( 'ABSPATH' ) || exit;

add_action( 'widgets_init', 'salon_register_widgets' );

function salon_register_widgets() {
	register_widget( 'Salon_Hours_Widget' );
}

/**
 * Bốn phương thức của mọi widget:
 *   __construct() khai báo id_base, tên · widget() in ra frontend · form() in form trong admin · update() sanitize khi lưu
 */
class Salon_Hours_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'salon_hours',        // id_base → option "widget_salon_hours", HTML id "salon_hours-2"
			'Salon: Giờ mở cửa',
			array(
				'description'           => 'Giờ mở cửa (lấy từ Settings → Salon Booking) và trạng thái đang mở/đóng.',
				'classname'             => 'widget-salon-hours',
				'show_instance_in_rest' => true, // cho block editor của màn Widgets đọc/sửa instance qua REST
			)
		);
	}

	/**
	 * Frontend.
	 *
	 * @param array $args     before_widget/after_widget/before_title/after_title do register_sidebar() của THEME khai báo.
	 * @param array $instance Cài đặt của widget này (đã qua update()).
	 */
	public function widget( $args, $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'show_button' => true ) );
		$title    = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base ); // filter chuẩn mọi widget đều chạy

		$open  = (string) salon_get_setting( 'open_time' );
		$close = (string) salon_get_setting( 'close_time' );
		$now   = wp_date( 'H:i' ); // giờ hiện tại theo timezone của site (không phải giờ server)
		$is_open = $now >= $open && $now < $close;

		wp_enqueue_style( 'salon-frontend' );

		// $args do theme cung cấp (HTML tin cậy) → in trực tiếp, đây là quy ước của Core.
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>
		<div class="salon-hours">
			<p class="salon-hours__time"><?php echo esc_html( $open . ' – ' . $close ); ?> <span>(tất cả các ngày)</span></p>
			<p class="salon-hours__status salon-hours__status--<?php echo $is_open ? 'open' : 'closed'; ?>">
				<?php echo $is_open ? 'Đang mở cửa' : 'Đã đóng cửa'; ?>
			</p>
			<?php
			$booking_url = salon_get_booking_url();
			if ( $instance['show_button'] && $booking_url ) :
				?>
				<a class="salon-hours__button" href="<?php echo esc_url( $booking_url ); ?>">Đặt lịch online</a>
			<?php endif; ?>
		</div>
		<?php
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Form trong admin. Dùng get_field_id()/get_field_name() để mỗi bản sao widget có name riêng
	 * (widget-salon_hours[2][title]) — cùng 1 widget có thể đặt nhiều lần ở nhiều sidebar.
	 *
	 * @param array $instance Cài đặt hiện tại.
	 * @return void
	 */
	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => 'Giờ mở cửa', 'show_button' => true ) );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">Tiêu đề:</label>
			<input class="widefat" type="text"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				value="<?php echo esc_attr( $instance['title'] ); ?>">
		</p>
		<p>
			<input type="checkbox" value="1"
				id="<?php echo esc_attr( $this->get_field_id( 'show_button' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'show_button' ) ); ?>"
				<?php checked( (bool) $instance['show_button'] ); ?>>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_button' ) ); ?>">Hiện nút "Đặt lịch online"</label>
		</p>
		<p class="description">Giờ mở cửa sửa ở Settings → Salon Booking.</p>
		<?php
	}

	/**
	 * Sanitize khi admin bấm Save. Nonce + capability (edit_theme_options) do Core kiểm tra trước khi gọi hàm này.
	 *
	 * @param array $new_instance Dữ liệu form vừa gửi.
	 * @param array $old_instance Dữ liệu cũ.
	 * @return array Dữ liệu sẽ lưu vào option widget_salon_hours.
	 */
	public function update( $new_instance, $old_instance ) {
		return array(
			'title'       => sanitize_text_field( $new_instance['title'] ?? '' ),
			'show_button' => ! empty( $new_instance['show_button'] ),
		);
	}
}
