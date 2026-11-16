<?php
/**
 * Card một dịch vụ — dùng lại ở trang chủ, /dich-vu/, "Dịch vụ khác".
 * Gọi bằng get_template_part( 'template-parts/card', 'service' ) bên trong một vòng loop.
 *
 * @package SalonTheme
 */

defined( 'ABSPATH' ) || exit;
?>
<article <?php post_class( 'card' ); ?>>
	<a class="card-media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'salon-card' ); ?>
		<?php else : ?>
			<span class="placeholder-media"><?php echo esc_html( mb_substr( get_the_title(), 0, 1 ) ); ?></span>
		<?php endif; ?>
	</a>

	<div class="card-body">
		<h3 class="card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
		<?php salon_theme_service_meta( get_the_ID() ); ?>
		<?php if ( has_excerpt() ) : ?>
			<p class="card-excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
		<?php endif; ?>
	</div>
</article>
