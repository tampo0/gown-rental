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
 * Интервал аренды в секундах [начало; конец).
 * С временем (Шымкент): 3 часа от начала в день date_from.
 * Без времени (другие города, вносит админ): целые дни с date_from по date_to включительно.
 */
function gr_interval( $date_from, $date_to, $time = '' ) {
	if ( $time ) {
		$start = strtotime( $date_from . ' ' . $time . ' UTC' );
		return array( $start, $start + gr_rental_hours() * HOUR_IN_SECONDS );
	}
	return array( strtotime( $date_from . ' 00:00 UTC' ), strtotime( $date_to . ' 00:00 UTC' ) + DAY_IN_SECONDS );
}

/**
 * Максимум одновременно занятых мантий в любой момент периода.
 *
 * Пиковая занятость, а не сумма: две аренды одна за другой (11:00–14:00 и 14:00–17:00)
 * одну мантию одновременно не занимают.
 *
 * @param string $time       Время начала (ЧЧ:ММ) или '' — тогда период целыми днями.
 * @param int    $exclude_id Бронь, которую не учитывать (при редактировании — саму себя).
 */
function gr_get_peak_usage( $color, $size, $from, $to, $exclude_id = 0, $time = '' ) {
	global $wpdb;
	$bookings = gr_bookings_table();
	$items    = gr_items_table();

	// Сначала грубо отбираем брони, чьи ДНИ пересекаются с нашими, — точное время проверим ниже.
	$statuses     = gr_active_statuses();
	$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

	$sql = "SELECT b.date_from, b.date_to, b.time_from, i.quantity
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

	list( $q_start, $q_end ) = gr_interval( $from, $to, $time );

	$spans = array();
	foreach ( $rows as $row ) {
		list( $start, $end ) = gr_interval( $row->date_from, $row->date_to, $row->time_from );
		if ( $start < $q_end && $end > $q_start ) {
			$spans[] = array( max( $start, $q_start ), $end, (int) $row->quantity );
		}
	}

	// Занятость максимальна в один из моментов начала (нашего или чужого) — проверяем их.
	$peak = 0;
	foreach ( array_merge( array( $q_start ), array_column( $spans, 0 ) ) as $moment ) {
		$used = 0;
		foreach ( $spans as $span ) {
			if ( $span[0] <= $moment && $moment < $span[1] ) {
				$used += $span[2];
			}
		}
		$peak = max( $peak, $used );
	}
	return $peak;
}

/**
 * Сколько мантий свободно на ВЕСЬ период аренды [$from; $to].
 * Если на складе уменьшили количество (химчистка, брак) — свободных станет меньше сразу.
 */
function gr_get_available( $color, $size, $from, $to, $exclude_id = 0, $time = '' ) {
	if ( ! gr_is_offered( $color, $size ) ) {
		return 0; // такого размера у этого цвета нет
	}
	$stock = gr_get_stock( $color, $size );
	$used  = gr_get_peak_usage( $color, $size, $from, $to, $exclude_id, $time );

	return max( 0, $stock - $used );
}

/**
 * Свободно на период для всех цветов и размеров сразу:
 * [ 'red' => [ 'kids' => 3, 'standard' => 10, 'giant' => 0 ], ... ]
 */
function gr_get_availability_matrix( $from, $to, $exclude_id = 0, $time = '' ) {
	$matrix = array();
	foreach ( array_keys( gr_colors() ) as $color ) {
		foreach ( array_keys( gr_color_sizes( $color ) ) as $size ) {
			$matrix[ $color ][ $size ] = gr_get_available( $color, $size, $from, $to, $exclude_id, $time );
		}
	}
	return $matrix;
}
