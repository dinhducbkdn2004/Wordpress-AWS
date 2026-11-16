<?php
/**
 * Một bài trong danh sách (blog, tìm kiếm, category).
 *
 * @package SalonTheme
 */

defined( 'ABSPATH' ) || exit;
?>
<article <?php post_class( 'post-item' ); ?>>
	<h2 class="post-item-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
	<p class="entry-meta"><?php echo esc_html( get_the_date() ); ?></p>
	<p><?php echo esc_html( get_the_excerpt() ); ?></p>
</article>
