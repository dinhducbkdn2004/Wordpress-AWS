<?php
/**
 * Phần đầu trang — được gọi bằng get_header() ở mọi template.
 *
 * @package SalonTheme
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); // BẮT BUỘC: nơi WP & plugin in <title>, CSS, JS, meta SEO... ?>
</head>

<body <?php body_class(); // class theo ngữ cảnh: home, single-salon_service, logged-in... ?>>
<?php wp_body_open(); // cho plugin chèn code ngay sau <body> (vd Google Tag Manager) ?>

<a class="skip-link screen-reader-text" href="#main">Bỏ qua, tới nội dung chính</a>

<header class="site-header">
	<div class="container header-inner">
		<div class="site-branding">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a class="site-title" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
			<?php endif; ?>
		</div>

		<button class="menu-toggle" type="button" aria-controls="site-navigation" aria-expanded="false">
			<span class="menu-toggle-icon" aria-hidden="true"></span>
			<span class="screen-reader-text">Mở menu</span>
		</button>

		<nav id="site-navigation" class="main-navigation" aria-label="Menu chính">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'menu_id'        => 'primary-menu',
					'container'      => false,
					'fallback_cb'    => 'salon_theme_menu_fallback',
				)
			);
			?>
			<a class="button header-cta" href="<?php echo esc_url( salon_theme_booking_url() ); ?>">Đặt lịch</a>
		</nav>
	</div>
</header>

<main id="main" class="site-main">
