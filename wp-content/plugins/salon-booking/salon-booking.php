<?php
/**
 * Plugin Name:       Salon Booking
 * Description:       Quản lý dịch vụ, nhân viên và lịch hẹn cho Salon/Nail.
 * Version:           0.4.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Đinh Đức
 * License:           GPL-2.0-or-later
 * Text Domain:       salon-booking
 *
 * File này là "bootstrap" DUY NHẤT: khai báo hằng, nạp file, đăng ký activation/deactivation.
 * Không làm việc nặng ở đây (không query, không echo) — mọi thứ chạy qua hook.
 *
 * @package SalonBooking
 */

// CI/CD test: first automatic deployment

defined( 'ABSPATH' ) || exit; // Chặn gọi trực tiếp http://.../salon-booking.php

const SALON_VERSION    = '0.4.0'; // version của plugin → dùng cho cache-busting asset
const SALON_DB_VERSION = '1.2';   // version của "dữ liệu cài đặt" (bảng, option, rewrite) → dùng cho upgrade
const SALON_FILE       = __FILE__;

// Code dùng ở mọi nơi (frontend, admin, REST, CLI).
require_once __DIR__ . '/includes/install.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/post-types.php';
require_once __DIR__ . '/includes/bookings.php';
require_once __DIR__ . '/includes/render.php';     // Phần 6: HTML dùng chung cho shortcode + block
require_once __DIR__ . '/includes/shortcodes.php';
require_once __DIR__ . '/includes/widgets.php';
require_once __DIR__ . '/includes/blocks.php';
require_once __DIR__ . '/includes/booking-validation.php'; // Phần 7: validate dùng chung (form, sau này REST)
require_once __DIR__ . '/includes/booking-form.php';       // Phần 7: shortcode form + xử lý submit qua admin-post.php

// Code chỉ dùng trong wp-admin → không nạp ở frontend (bớt parse PHP mỗi request).
if ( is_admin() ) {
	require_once __DIR__ . '/admin/settings-page.php';
	require_once __DIR__ . '/admin/service-meta-box.php';
}

// PHẢI đăng ký ở top-level của file chính, KHÔNG đặt trong hook nào:
// lúc bấm Activate, WP include file này rồi bắn "activate_salon-booking/salon-booking.php" ngay,
// không chờ tới plugins_loaded/init.
register_activation_hook( SALON_FILE, 'salon_activate' );
register_deactivation_hook( SALON_FILE, 'salon_deactivate' );
// Gỡ cài đặt: dùng file uninstall.php (không dùng register_uninstall_hook).

add_action( 'plugins_loaded', 'salon_maybe_upgrade' );
