<?php
/**
 * Склад и проверка доступности мантий по датам.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Весь склад одной таблицей: [ 'red' => [ 'kids' => 5, ... ], ... ].
 */
function gr_get_stock_table() {
	global $wpdb;
	$table = gr_inventory_table();

	// Сначала нули для всех комбинаций — на случай, если строки нет.
	$result = array();
	foreach ( array_keys( gr_colors() ) as $color ) {
		foreach ( array_keys( gr_sizes() ) as $size ) {
			$result[ $color ][ $size ] = 0;
		}
	}

	$rows = $wpdb->get_results( "SELECT color, size, quantity FROM {$table}" );
	foreach ( $rows as $row ) {
		if ( isset( $result[ $row->color ][ $row->size ] ) ) {
			$result[ $row->color ][ $row->size ] = (int) $row->quantity;
		}
	}
	return $result;
}

/** Сколько всего мантий данного цвета и размера. */
function gr_get_stock( $color, $size ) {
	global $wpdb;
	$table = gr_inventory_table();
	return (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT quantity FROM {$table} WHERE color = %s AND size = %s", $color, $size )
	);
}

/** Записать количество на складе (создаст строку, если её нет). */
function gr_set_stock( $color, $size, $quantity ) {
	global $wpdb;
	$table = gr_inventory_table();
	$wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$table} (color, size, quantity, updated_at) VALUES (%s, %s, %d, %s)
			 ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), updated_at = VALUES(updated_at)",
			$color,
			$size,
			max( 0, (int) $quantity ),
			current_time( 'mysql' )
		)
	);
}

/**
 * Максимальное число занятых мантий в любой день периода [$from; $to].
 *
 * Бронь занимает мантию с date_from по date_to включительно.
 * Считаем по дням, а не просто суммой: две брони, которые идут одна за другой
 * (например, 20–22 и 23–25), не должны занимать две мантии одновременно.
 *
 * @param int $exclude_id  Бронь, которую не учитывать (при редактировании — саму себя).
 */
function gr_get_peak_usage( $color, $size, $from, $to, $exclude_id = 0 ) {
	global $wpdb;
	$bookings = gr_bookings_table();
	$items    = gr_items_table();

	// Берём позиции нужного цвета и размера из активных броней,
	// чей период пересекается с [$from; $to]: бронь начинается не позже
	// нашего конца и заканчивается не раньше нашего начала.
	$statuses     = gr_active_statuses();
	$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

	$sql = "SELECT b.date_from, b.date_to, i.quantity
		FROM {$items} i
		INNER JOIN {$bookings} b ON b.id = i.booking_id
		WHERE i.color = %s AND i.size = %s AND b.id <> %d
		AND b.status IN ({$placeholders})
		AND b.date_from <= %s AND b.date_to >= %s";

	$params = array_merge( array( $color, $size, (int) $exclude_id ), $statuses, array( $to, $from ) );
	$rows   = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

	if ( ! $rows ) {
		return 0;
	}

	// Идём по дням периода и ищем день с наибольшей занятостью.
	$peak = 0;
	$day  = new DateTime( $from );
	$end  = new DateTime( $to );
	while ( $day <= $end ) {
		$d    = $day->format( 'Y-m-d' );
		$used = 0;
		foreach ( $rows as $row ) {
			if ( $row->date_from <= $d && $row->date_to >= $d ) {
				$used += (int) $row->quantity;
			}
		}
		$peak = max( $peak, $used );
		$day->modify( '+1 day' );
	}
	return $peak;
}

/**
 * Сколько мантий свободно на ВЕСЬ период аренды [$from; $to].
 * Если на складе уменьшили количество (химчистка, брак) — свободных станет меньше сразу.
 */
function gr_get_available( $color, $size, $from, $to, $exclude_id = 0 ) {
	if ( ! gr_is_offered( $color, $size ) ) {
		return 0; // такого размера у этого цвета нет
	}
	$stock = gr_get_stock( $color, $size );
	$used  = gr_get_peak_usage( $color, $size, $from, $to, $exclude_id );

	return max( 0, $stock - $used );
}

/**
 * Свободно на период для всех цветов и размеров сразу:
 * [ 'red' => [ 'kids' => 3, 'standard' => 10, 'long' => 0 ], ... ]
 */
function gr_get_availability_matrix( $from, $to, $exclude_id = 0 ) {
	$matrix = array();
	foreach ( array_keys( gr_colors() ) as $color ) {
		foreach ( array_keys( gr_color_sizes( $color ) ) as $size ) {
			$matrix[ $color ][ $size ] = gr_get_available( $color, $size, $from, $to, $exclude_id );
		}
	}
	return $matrix;
}
