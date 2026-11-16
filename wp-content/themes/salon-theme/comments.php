<?php
/**
 * Danh sách bình luận + form — được nạp bởi comments_template() trong single.php.
 * Thiếu file này, WP dùng wp-includes/theme-compat/comments.php và báo Deprecated.
 *
 * @package SalonTheme
 */

defined( 'ABSPATH' ) || exit;

// Bài có mật khẩu mà khách chưa nhập → không hiện bình luận.
if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="comments-area">
	<?php if ( have_comments() ) : ?>
		<h2 class="comments-title">
			<?php
			/* translators: %d: số bình luận */
			printf( esc_html( _n( '%d bình luận', '%d bình luận', get_comments_number() ) ), (int) get_comments_number() );
			?>
		</h2>

		<ol class="comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 40,
				)
			);
			?>
		</ol>

		<?php the_comments_navigation(); ?>
	<?php endif; ?>

	<?php comment_form(); ?>
</section>
