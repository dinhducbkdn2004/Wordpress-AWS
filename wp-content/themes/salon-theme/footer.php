<?php
/**
 * Phần cuối trang — được gọi bằng get_footer().
 *
 * @package SalonTheme
 */

defined( 'ABSPATH' ) || exit;
?>
</main>

<footer class="site-footer">
	<div class="container footer-inner">
		<div>
			<p class="footer-title"><?php bloginfo( 'name' ); ?></p>
			<p>Mở cửa 8:00 – 20:00 mỗi ngày</p>
		</div>

		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'footer',
				'container'      => 'nav',
				'container_attr' => array( 'aria-label' => 'Menu chân trang' ),
				'depth'          => 1,
				'fallback_cb'    => false, // chưa gán menu → không in gì
			)
		);
		?>

		<p class="copyright">&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
	</div>
</footer>

<?php wp_footer(); // BẮT BUỘC: nơi in JS ở footer, admin bar... ?>
</body>
</html>
