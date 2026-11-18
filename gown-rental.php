<?php
/**
 * Plugin Name: Gown Rental — Прокат мантий
 * Description: Каталог мантий с бронированием нескольких цветов и размеров, доставка, склад и управление бронями.
 * Version:     0.15.2
 * Author:      Tamerlan
 * Text Domain: gown-rental
 * Requires PHP: 7.4
 */

// Защита: файл нельзя открыть напрямую по адресу, только через WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Версия плагина. При её изменении таблицы в базе обновляются автоматически.
define( 'GR_VERSION', '0.15.2' );
define( 'GR_PATH', plugin_dir_path( __FILE__ ) );
define( 'GR_URL', plugin_dir_url( __FILE__ ) );

// Логика (работает и на сайте, и в админке).
require_once GR_PATH . 'includes/config.php';
require_once GR_PATH . 'includes/install.php';
require_once GR_PATH . 'includes/inventory.php';
require_once GR_PATH . 'includes/bookings.php';
require_once GR_PATH . 'includes/emails.php';

// Форма бронирования на сайте: шорткод [gown_booking] и AJAX-запросы.
require_once GR_PATH . 'includes/public.php';

// Главная страница: шорткод [gown_landing].
require_once GR_PATH . 'includes/landing.php';

// Страницы «Условия аренды» и «Контакты»: шорткоды [gown_terms], [gown_contacts].
require_once GR_PATH . 'includes/pages.php';

// Страница «Нам доверяют» [gown_trust] и полоса с логотипами вузов на главной.
require_once GR_PATH . 'includes/trust.php';

// Страницы админки.
if ( is_admin() ) {
	require_once GR_PATH . 'admin/menu.php';
	require_once GR_PATH . 'admin/inventory-page.php';
	require_once GR_PATH . 'admin/bookings-page.php';
}

register_activation_hook( __FILE__, 'gr_activate' );
