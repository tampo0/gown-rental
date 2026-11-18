<?php
/**
 * Брони: получение, проверка, сохранение, смена статуса и удаление.
 * Одна бронь может содержать несколько позиций (разные цвета и размеры).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Позиции одной брони. */
function gr_get_booking_items( $booking_id ) {
	global $wpdb;
	$table = gr_items_table();
	return $wpdb->get_results(
		$wpdb->prepare( "SELECT color, size, quantity FROM {$table} WHERE booking_id = %d ORDER BY id", $booking_id )
	);
}

/** Одна бронь по номеру вместе с позициями (или null). */
function gr_get_booking( $id ) {
	global $wpdb;
	$table   = gr_bookings_table();
	$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	if ( $booking ) {
		$booking->items = gr_get_booking_items( $booking->id );
	}
	return $booking;
}

/**
 * Список броней с фильтрами. У каждой брони есть ->items.
 *
 * @param array $args status, color, size, search (имя/телефон), date (бронь действует в этот день).
 */
function gr_get_bookings( $args = array() ) {
	global $wpdb;
	$table  = gr_bookings_table();
	$items  = gr_items_table();
	$where  = array( '1=1' );
	$params = array();

	if ( ! empty( $args['status'] ) ) {
		$where[]  = 'b.status = %s';
		$params[] = $args['status'];
	}
	if ( ! empty( $args['color'] ) ) {
		$where[]  = "EXISTS (SELECT 1 FROM {$items} i WHERE i.booking_id = b.id AND i.color = %s)";
		$params[] = $args['color'];
	}
	if ( ! empty( $args['size'] ) ) {
		$where[]  = "EXISTS (SELECT 1 FROM {$items} i WHERE i.booking_id = b.id AND i.size = %s)";
		$params[] = $args['size'];
	}
	if ( ! empty( $args['date'] ) ) {
		$where[]  = 'b.date_from <= %s AND b.date_to >= %s';
		$params[] = $args['date'];
		$params[] = $args['date'];
	}
	if ( ! empty( $args['search'] ) ) {
		$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		$where[]  = '(b.customer_name LIKE %s OR b.phone LIKE %s)';
		$params[] = $like;
		$params[] = $like;
	}

	$sql = "SELECT b.* FROM {$table} b WHERE " . implode( ' AND ', $where ) . ' ORDER BY b.date_from DESC, b.id DESC LIMIT 200';
	if ( $params ) {
		$sql = $wpdb->prepare( $sql, $params );
	}
	$bookings = $wpdb->get_results( $sql );

	// Позиции всех найденных броней — одним запросом, а не по запросу на бронь.
	if ( $bookings ) {
		$by_id = array();
		foreach ( $bookings as $b ) {
			$b->items          = array();
			$by_id[ (int) $b->id ] = $b;
		}
		$ids  = implode( ',', array_map( 'intval', array_keys( $by_id ) ) );
		$rows = $wpdb->get_results( "SELECT booking_id, color, size, quantity FROM {$items} WHERE booking_id IN ({$ids}) ORDER BY id" );
		foreach ( $rows as $row ) {
			$by_id[ (int) $row->booking_id ]->items[] = $row;
		}
	}
	return $bookings;
}

/** Очищает данные из формы: убирает HTML, лишние пробелы и т.п. */
function gr_sanitize_booking( $raw ) {
	$get = function ( $key ) use ( $raw ) {
		return isset( $raw[ $key ] ) ? wp_unslash( $raw[ $key ] ) : '';
	};

	$delivery = ! empty( $raw['delivery'] ) ? 1 : 0;
	$pickup   = ! empty( $raw['pickup'] ) ? 1 : 0;

	return array(
		'customer_name' => sanitize_text_field( $get( 'customer_name' ) ),
		'phone'         => sanitize_text_field( $get( 'phone' ) ),
		'email'         => sanitize_email( $get( 'email' ) ),
		'date_from'     => sanitize_text_field( $get( 'date_from' ) ),
		'date_to'       => sanitize_text_field( $get( 'date_to' ) ),
		'delivery'      => $delivery,
		'delivery_time' => $delivery ? sanitize_text_field( $get( 'delivery_time' ) ) : '',
		'pickup'        => $pickup,
		'pickup_time'   => $pickup ? sanitize_text_field( $get( 'pickup_time' ) ) : '',
		'address'       => ( $delivery || $pickup ) ? sanitize_text_field( $get( 'address' ) ) : '',
		'source'        => sanitize_key( $get( 'source' ) ),
		'status'        => sanitize_key( $get( 'status' ) ),
		'note'          => sanitize_textarea_field( $get( 'note' ) ),
		'items'         => gr_normalize_items( $get( 'items' ) ),
	);
}

/**
 * Проверяет, хватает ли мантий на каждую позицию брони.
 * Возвращает массив ошибок (пустой — всё хватает).
 *
 * @param int $exclude_id Бронь, которую не учитывать (при редактировании — саму себя).
 */
function gr_check_items_availability( $items, $from, $to, $exclude_id = 0 ) {
	$errors = array();
	foreach ( $items as $item ) {
		$item      = (array) $item;
		$available = gr_get_available( $item['color'], $item['size'], $from, $to, $exclude_id );
		if ( (int) $item['quantity'] > $available ) {
			$errors[] = sprintf(
				'Недостаточно мантий: %s, %s на %s – %s. Свободно: %d, нужно: %d.',
				gr_label( gr_colors(), $item['color'] ),
				gr_label( gr_sizes(), $item['size'] ),
				gr_format_date( $from ),
				gr_format_date( $to ),
				$available,
				$item['quantity']
			);
		}
	}
	return $errors;
}

/**
 * Проверяет бронь. Возвращает массив ошибок (пустой — всё в порядке).
 *
 * @param int $id Номер редактируемой брони (0 — новая).
 */
function gr_validate_booking( $data, $id = 0 ) {
	$errors = array();

	if ( '' === $data['customer_name'] ) {
		$errors[] = 'Укажите имя клиента.';
	}
	if ( strlen( preg_replace( '/\D/', '', $data['phone'] ) ) < 10 ) {
		$errors[] = 'Укажите телефон (минимум 10 цифр).';
	}
	if ( ! isset( gr_statuses()[ $data['status'] ] ) ) {
		$errors[] = 'Неизвестный статус.';
	}
	if ( ! isset( gr_sources()[ $data['source'] ] ) ) {
		$errors[] = 'Неизвестный источник заявки.';
	}

	// Позиции.
	if ( ! $data['items'] ) {
		$errors[] = 'Выберите хотя бы одну мантию: цвет, размер и количество.';
	}
	foreach ( $data['items'] as $item ) {
		if ( ! isset( gr_colors()[ $item['color'] ] ) || ! isset( gr_sizes()[ $item['size'] ] ) ) {
			$errors[] = 'В заказе есть мантия с неизвестным цветом или размером.';
			break;
		}
		if ( ! gr_is_offered( $item['color'], $item['size'] ) ) {
			$errors[] = 'Мантии «' . gr_label( gr_colors(), $item['color'] ) . ', ' . mb_strtolower( gr_label( gr_sizes(), $item['size'] ) ) . '» нет в прокате.';
			break;
		}
		if ( $item['quantity'] > gr_max_item_quantity() ) {
			$errors[] = 'Слишком большое количество в одной позиции.';
			break;
		}
	}

	// Даты.
	$dates_ok = gr_is_valid_date( $data['date_from'] ) && gr_is_valid_date( $data['date_to'] );
	if ( ! $dates_ok ) {
		$errors[] = 'Укажите даты аренды.';
	} elseif ( $data['date_to'] < $data['date_from'] ) {
		$errors[] = 'Дата возврата не может быть раньше даты получения.';
	} elseif ( gr_count_days( $data['date_from'], $data['date_to'] ) > gr_max_rental_days() ) {
		$errors[] = 'Максимальный срок аренды — ' . gr_max_rental_days() . ' дней.';
	}

	// Доставка и забор.
	if ( ( $data['delivery'] || $data['pickup'] ) && '' === $data['address'] ) {
		$errors[] = 'Укажите адрес доставки.';
	}
	if ( $data['delivery'] && ! gr_is_valid_time( $data['delivery_time'] ) ) {
		$errors[] = 'Укажите точное время доставки.';
	}
	if ( $data['pickup'] && '' !== $data['pickup_time'] && ! gr_is_valid_time( $data['pickup_time'] ) ) {
		$errors[] = 'Неверное время, когда забрать мантии.';
	}

	// Главная проверка: хватит ли мантий на эти даты.
	// Делаем её, только если остальное верно и бронь «активная».
	if ( ! $errors && in_array( $data['status'], gr_active_statuses(), true ) ) {
		$errors = gr_check_items_availability( $data['items'], $data['date_from'], $data['date_to'], $id );
	}

	return $errors;
}

/**
 * Блокировка на время «проверить наличие → записать бронь»,
 * чтобы два клиента одновременно не забрали последнюю мантию.
 */
function gr_lock() {
	global $wpdb;
	return (bool) $wpdb->get_var( "SELECT GET_LOCK('gr_booking_lock', 10)" );
}

function gr_unlock() {
	global $wpdb;
	$wpdb->get_var( "SELECT RELEASE_LOCK('gr_booking_lock')" );
}

/**
 * Сохраняет бронь (новую или существующую) вместе с позициями.
 * Возвращает номер брони или WP_Error со списком ошибок.
 */
function gr_save_booking( $raw, $id = 0 ) {
	global $wpdb;
	$table = gr_bookings_table();
	$data  = gr_sanitize_booking( $raw );

	if ( ! gr_lock() ) {
		return new WP_Error( 'gr_busy', 'Сервер занят, попробуйте ещё раз через несколько секунд.' );
	}

	$errors = gr_validate_booking( $data, $id );
	if ( $errors ) {
		gr_unlock();
		$error = new WP_Error();
		foreach ( $errors as $message ) {
			$error->add( 'gr_invalid', $message );
		}
		$error->add_data( $data );
		return $error;
	}

	$items  = $data['items'];
	$totals = gr_calc_totals( $items, $data['date_from'], $data['date_to'], $data['delivery'], $data['pickup'] );
	unset( $data['items'] );

	$data['rental_price'] = $totals['rental'];
	$data['delivery_fee'] = $totals['delivery_fee'];
	$data['pickup_fee']   = $totals['pickup_fee'];
	$data['total_price']  = $totals['total'];

	$now = current_time( 'mysql' );
	if ( $id ) {
		$data['updated_at'] = $now;
		$wpdb->update( $table, $data, array( 'id' => $id ) );
	} else {
		$data['created_at'] = $now;
		$wpdb->insert( $table, $data );
		$id = (int) $wpdb->insert_id;
	}

	gr_replace_booking_items( $id, $items );
	gr_unlock();

	return (int) $id;
}

/** Перезаписывает позиции брони. */
function gr_replace_booking_items( $booking_id, $items ) {
	global $wpdb;
	$table = gr_items_table();
	$wpdb->delete( $table, array( 'booking_id' => $booking_id ) );
	foreach ( $items as $item ) {
		$wpdb->insert(
			$table,
			array(
				'booking_id' => $booking_id,
				'color'      => $item['color'],
				'size'       => $item['size'],
				'quantity'   => $item['quantity'],
			)
		);
	}
}

/**
 * Быстрая смена статуса (из списка броней).
 * Если бронь снова становится «активной» (например, «Отменена» → «Подтверждена»),
 * проверяем, что мантии на эти даты ещё свободны.
 *
 * @return true|WP_Error
 */
function gr_set_booking_status( $id, $status ) {
	global $wpdb;

	if ( ! isset( gr_statuses()[ $status ] ) ) {
		return new WP_Error( 'gr_invalid', 'Неизвестный статус.' );
	}
	$booking = gr_get_booking( $id );
	if ( ! $booking ) {
		return new WP_Error( 'gr_not_found', 'Бронь не найдена.' );
	}
	if ( $booking->status === $status ) {
		return true;
	}

	if ( ! gr_lock() ) {
		return new WP_Error( 'gr_busy', 'Сервер занят, попробуйте ещё раз.' );
	}

	if ( in_array( $status, gr_active_statuses(), true ) ) {
		$errors = gr_check_items_availability( $booking->items, $booking->date_from, $booking->date_to, $booking->id );
		if ( $errors ) {
			gr_unlock();
			return new WP_Error( 'gr_invalid', implode( "\n", $errors ) );
		}
	}

	$wpdb->update(
		gr_bookings_table(),
		array( 'status' => $status, 'updated_at' => current_time( 'mysql' ) ),
		array( 'id' => $id )
	);
	gr_unlock();
	return true;
}

/** Удаляет бронь вместе с позициями. */
function gr_delete_booking( $id ) {
	global $wpdb;
	$wpdb->delete( gr_items_table(), array( 'booking_id' => (int) $id ) );
	return (bool) $wpdb->delete( gr_bookings_table(), array( 'id' => (int) $id ) );
}
