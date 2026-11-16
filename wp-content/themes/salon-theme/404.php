<?php
/**
 * Không tìm thấy trang. WP chọn file này khi main query không có kết quả (is_404()).
 *
 * @package SalonTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="container container-narrow section text-center">
	<p class="eyebrow">Lỗi 404</p>
	<h1 class="page-title">Không tìm thấy trang</h1>
	<p>Trang bạn tìm có thể đã bị đổi tên hoặc xoá. Thử tìm kiếm:</p>
	<?php get_search_form(); ?>
	<p><a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>">Về trang chủ</a></p>
</div>

<?php
get_footer();
