<?php
/**
 * Установка и обновление плагина: создание таблиц в базе данных.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Запускается при активации плагина. */
function gr_activate() {
	gr_install();
}

/**
 * Если плагин обновился (версия в коде новее, чем записана в базе) —
 * обновляем таблицы автоматически, без повторной активации.
 */
function gr_maybe_upgrade() {
	if ( get_option( 'gr_db_version' ) !== GR_VERSION ) {
		gr_install();
	}
}
add_action( 'plugins_loaded', 'gr_maybe_upgrade' );

/** Создаёт/обновляет таблицы и заполняет склад начальными строками. */
function gr_install() {
	global $wpdb;

	$inventory = gr_inventory_table();
	$bookings  = gr_bookings_table();
	$items     = gr_items_table();
	$charset   = $wpdb->get_charset_collate();

	// В версии 0.1.0 таблица броней была устроена совсем иначе (по gown_id).
	// Она была пустой, поэтому безопасно пересоздаём её.
	if ( '0.1.0' === get_option( 'gr_db_version' ) ) {
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bookings ) );
		if ( $exists && 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings}" ) ) {
			$wpdb->query( "DROP TABLE {$bookings}" );
		}
	}

	// dbDelta — функция WordPress: создаёт таблицу или добавляет новые поля.
	// Формат строгий: каждое поле на своей строке, два пробела после PRIMARY KEY.

	// Склад: сколько мантий каждого цвета и размера у нас всего.
	$sql_inventory = "CREATE TABLE {$inventory} (
		id int(10) unsigned NOT NULL AUTO_INCREMENT,
		color varchar(20) NOT NULL,
		size varchar(20) NOT NULL,
		quantity smallint(5) unsigned NOT NULL DEFAULT 0,
		updated_at datetime NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY color_size (color,size)
	) {$charset};";

	// Брони: клиент, даты, доставка, суммы. Сами мантии — в таблице позиций.
	$sql_bookings = "CREATE TABLE {$bookings} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		date_from date NOT NULL,
		date_to date NOT NULL,
		customer_name varchar(100) NOT NULL,
		phone varchar(30) NOT NULL,
		email varchar(100) NOT NULL DEFAULT '',
		time_from varchar(5) NOT NULL DEFAULT '',
		delivery tinyint(1) NOT NULL DEFAULT 0,
		delivery_time varchar(5) NOT NULL DEFAULT '',
		pickup tinyint(1) NOT NULL DEFAULT 0,
		pickup_time varchar(5) NOT NULL DEFAULT '',
		address varchar(255) NOT NULL DEFAULT '',
		rental_price int(10) unsigned NOT NULL DEFAULT 0,
		delivery_fee int(10) unsigned NOT NULL DEFAULT 0,
		pickup_fee int(10) unsigned NOT NULL DEFAULT 0,
		total_price int(10) unsigned NOT NULL DEFAULT 0,
		source varchar(20) NOT NULL DEFAULT 'site',
		status varchar(20) NOT NULL DEFAULT 'new',
		note text NULL,
		created_at datetime NOT NULL,
		updated_at datetime NULL,
		PRIMARY KEY  (id),
		KEY dates (date_from,date_to),
		KEY status (status)
	) {$charset};";

	// Позиции брони: «2 бордовых детских + 3 чёрных стандарт» — это две строки.
	$sql_items = "CREATE TABLE {$items} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		booking_id bigint(20) unsigned NOT NULL,
		color varchar(20) NOT NULL,
		size varchar(20) NOT NULL,
		quantity smallint(5) unsigned NOT NULL DEFAULT 1,
		PRIMARY KEY  (id),
		KEY booking_id (booking_id),
		KEY color_size (color,size)
	) {$charset};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_inventory );
	dbDelta( $sql_bookings );
	dbDelta( $sql_items );

	gr_migrate_single_item_bookings();

	// Склад: по строке на каждую комбинацию «цвет + размер» (4 × 3 = 12).
	// INSERT IGNORE не трогает строки, которые уже есть.
	foreach ( array_keys( gr_colors() ) as $color ) {
		foreach ( array_keys( gr_color_sizes( $color ) ) as $size ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$inventory} (color, size, quantity) VALUES (%s, %s, 0)",
					$color,
					$size
				)
			);
		}
	}

	// Настройки по умолчанию (add_option не перезаписывает существующие).
	add_option( 'gr_delivery_fee', 3000 );
	add_option( 'gr_pickup_address', 'г. Шымкент, Спортивный микрорайон, 4/11' );
	add_option( 'gr_pickup_hours', 'Каждый день 9:30–18:00' );
	add_option( 'gr_2gis_url', 'https://2gis.kz/shymkent/geo/70000001114627327' );
	// Порог бесплатной доставки: раньше было «больше N», теперь «от N».
	if ( false === get_option( 'gr_free_delivery_from', false ) ) {
		$old = get_option( 'gr_free_delivery_over', false );
		add_option( 'gr_free_delivery_from', false === $old ? 20 : (int) $old + 1 );
	}
	delete_option( 'gr_free_delivery_over' );

	// v0.14: доставка только от 100 мантий, своя почта для заявок.
	if ( version_compare( (string) get_option( 'gr_db_version', '0' ), '0.14.0', '<' ) ) {
		update_option( 'gr_free_delivery_from', 100 );
	}
	add_option( 'gr_contact_email', 'Aizhansaiymova@gmail.com' );
	delete_option( 'gr_buffer_days' ); // убрано в 0.3.0

	update_option( 'gr_db_version', GR_VERSION );
}

/**
 * До версии 0.5.0 в одной брони была одна мантия: поля color, size, quantity
 * лежали прямо в таблице броней. Переносим их в таблицу позиций и удаляем старые поля.
 */
function gr_migrate_single_item_bookings() {
	global $wpdb;
	$bookings = gr_bookings_table();
	$items    = gr_items_table();

	if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$bookings} LIKE 'color'" ) ) {
		return; // уже перенесено
	}

	// 1. Каждая старая бронь → одна позиция (если ещё не перенесена).
	$wpdb->query(
		"INSERT INTO {$items} (booking_id, color, size, quantity)
		 SELECT b.id, b.color, b.size, b.quantity FROM {$bookings} b
		 WHERE NOT EXISTS (SELECT 1 FROM {$items} i WHERE i.booking_id = b.id)"
	);

	// 2. Старые поля больше не нужны.
	if ( $wpdb->get_var( "SHOW INDEX FROM {$bookings} WHERE Key_name = 'lookup'" ) ) {
		$wpdb->query( "ALTER TABLE {$bookings} DROP INDEX lookup" );
	}
	$wpdb->query( "ALTER TABLE {$bookings} DROP COLUMN color, DROP COLUMN size, DROP COLUMN quantity" );

	// 3. Посчитать суммы для старых броней.
	$ids = $wpdb->get_col( "SELECT id FROM {$bookings} WHERE total_price = 0" );
	foreach ( $ids as $id ) {
		$booking = gr_get_booking( $id );
		$totals  = gr_calc_totals( $booking->items, $booking->date_from, $booking->date_to, false, false );
		$wpdb->update(
			$bookings,
			array( 'rental_price' => $totals['rental'], 'total_price' => $totals['total'] ),
			array( 'id' => $id )
		);
	}
}
