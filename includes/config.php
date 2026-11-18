<?php
/**
 * Справочники и общие функции плагина: цвета, размеры, статусы, цены, даты.
 * Всё, что может поменяться, собрано здесь в одном месте.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Цвета мантий: ключ хранится в базе, значение показывается людям. */
function gr_colors() {
	return array(
		'red'    => 'Бордовый',
		'blue'   => 'Тёмно-синий',
		'green'  => 'Изумруд',
		'purple' => 'Фиолетовый',
		'black'  => 'Чёрный',
	);
}

/** Цвета для кружков-образцов и заглушек фото. */
function gr_color_hex() {
	return array(
		'red'    => '#7d1d2c',
		'blue'   => '#262a5c',
		'green'  => '#0f5a46',
		'purple' => '#4a2346',
		'black'  => '#1b1b1d',
	);
}

/** Размеры мантий. */
function gr_sizes() {
	return array(
		'kids'     => 'Детский',
		'standard' => 'Стандарт',
		'giant'    => 'Великан',
	);
}

/** Размеры, которые есть у цвета: детский — у всех, кроме фиолетового; великан — только бордовый и изумруд. */
function gr_color_sizes( $color ) {
	$map = array(
		'red'    => array( 'kids', 'standard', 'giant' ),
		'blue'   => array( 'kids', 'standard' ),
		'green'  => array( 'kids', 'standard', 'giant' ),
		'purple' => array( 'standard' ),
		'black'  => array( 'kids', 'standard' ),
	);
	return array_intersect_key( gr_sizes(), array_flip( $map[ $color ] ?? array() ) );
}

/** Есть ли такая мантия (цвет + размер) в прокате вообще. */
function gr_is_offered( $color, $size ) {
	return isset( gr_color_sizes( $color )[ $size ] );
}

/** Статусы брони. */
function gr_statuses() {
	return array(
		'new'       => 'Новая',
		'confirmed' => 'Подтверждена',
		'issued'    => 'Выдана',
		'returned'  => 'Возвращена',
		'cancelled' => 'Отменена',
	);
}

/** Статусы, при которых мантии считаются занятыми. */
function gr_active_statuses() {
	return array( 'new', 'confirmed', 'issued' );
}

/** Откуда пришла заявка. */
function gr_sources() {
	return array(
		'site'     => 'Сайт',
		'whatsapp' => 'WhatsApp',
		'phone'    => 'Телефон',
		'visit'    => 'Лично',
	);
}

/** Подпись для ключа из справочника (или сам ключ, если не найден). */
function gr_label( $list, $key ) {
	return isset( $list[ $key ] ) ? $list[ $key ] : $key;
}

/* -------------------------------------------------------------------------
 * Цены и настройки (меняются в «Прокат мантий → Настройки»)
 * ---------------------------------------------------------------------- */

/**
 * Цена одной взрослой мантии за аренду в зависимости от количества в брони:
 * [ от скольки мантий => цена ]. Меняется в «Прокат мантий → Настройки».
 */
function gr_default_price_tiers() {
	return array( 1 => 5000, 3 => 3000, 5 => 2500, 10 => 2000 );
}

function gr_price_tiers() {
	$tiers = get_option( 'gr_price_tiers' );
	if ( ! is_array( $tiers ) || ! $tiers ) {
		return gr_default_price_tiers();
	}
	ksort( $tiers );
	return $tiers;
}

/** Детская мантия — одна цена при любом количестве (но считается в общее количество). */
function gr_kids_price() {
	return (int) get_option( 'gr_kids_price', 2000 );
}

/** Цена одной мантии при таком количестве в брони. */
function gr_unit_price( $qty ) {
	$price = 0;
	foreach ( gr_price_tiers() as $from => $tier_price ) {
		if ( $qty >= $from ) {
			$price = $tier_price;
		}
	}
	return $price;
}

/** От скольки мантий одна — бесплатно для организатора. */
function gr_free_gown_from() {
	return (int) get_option( 'gr_free_gown_from', 20 );
}

/** Длительность аренды в Шымкенте (часы) и время, когда можно начать. */
function gr_rental_hours() {
	return 3;
}

/** Начало аренды: с 9:30 до 15:00 каждые 30 минут — вернуть нужно до 18:00. */
function gr_start_times() {
	$times = array();
	for ( $t = strtotime( '09:30 UTC', 0 ); $t <= strtotime( '15:00 UTC', 0 ); $t += 30 * MINUTE_IN_SECONDS ) {
		$times[] = gmdate( 'H:i', $t );
	}
	return $times;
}

/** «9:30 – 15:00» для текстов. */
function gr_start_window() {
	$times = gr_start_times();
	return ltrim( $times[0], '0' ) . ' до ' . end( $times );
}

/** Доставки нет — только самовывоз; доставка (бесплатно) возможна от этого количества. */
function gr_delivery_fee() {
	return 0;
}

/** Адрес пункта самовывоза (меняется в «Настройках»). */
function gr_pickup_address() {
	return (string) get_option( 'gr_pickup_address', 'г. Шымкент, Спортивный микрорайон, 4/11' );
}

/** Часы работы пункта самовывоза. */
function gr_pickup_hours() {
	return (string) get_option( 'gr_pickup_hours', 'Каждый день 9:30–18:00' );
}

/** Ссылка на пункт самовывоза в 2ГИС. */
function gr_2gis_url() {
	return (string) get_option( 'gr_2gis_url', 'https://2gis.kz/shymkent/geo/70000001114627327' );
}

/** Доставка возможна (бесплатно) от этого количества мантий в брони. */
function gr_free_delivery_from() {
	return (int) get_option( 'gr_free_delivery_from', 100 );
}

/** Email, на который приходят брони и сообщения с сайта (меняется в «Настройках»). */
function gr_contact_email() {
	return (string) get_option( 'gr_contact_email', 'Aizhansaiymova@gmail.com' );
}

/** Ссылка на фото мантии данного цвета (или пустая строка). */
function gr_photo_url( $color, $size = 'large' ) {
	$id = (int) get_option( 'gr_photo_' . $color, 0 );
	return $id ? (string) wp_get_attachment_image_url( $id, $size ) : '';
}

/** Максимальная длительность одной аренды в днях. */
function gr_max_rental_days() {
	return 30;
}

/** Максимальное количество мантий одной позиции (цвет + размер). */
function gr_max_item_quantity() {
	return 500;
}

/* -------------------------------------------------------------------------
 * Таблицы
 * ---------------------------------------------------------------------- */

function gr_inventory_table() {
	global $wpdb;
	return $wpdb->prefix . 'gr_inventory';
}

function gr_bookings_table() {
	global $wpdb;
	return $wpdb->prefix . 'gr_bookings';
}

/** Позиции брони: одна бронь — много строк «цвет + размер + количество». */
function gr_items_table() {
	global $wpdb;
	return $wpdb->prefix . 'gr_booking_items';
}

/* -------------------------------------------------------------------------
 * Позиции заказа и расчёт стоимости
 * ---------------------------------------------------------------------- */

/**
 * Приводит позиции заказа к единому виду:
 * [ [ 'color' => 'red', 'size' => 'kids', 'quantity' => 2 ], ... ]
 *
 * Принимает массив из формы админки (items[0][color]...) или JSON-строку с сайта.
 * Одинаковые «цвет + размер» складываются, позиции с количеством 0 убираются.
 */
function gr_normalize_items( $raw ) {
	if ( is_string( $raw ) ) {
		$raw = json_decode( $raw, true );
	}
	if ( ! is_array( $raw ) ) {
		return array();
	}

	$merged = array();
	foreach ( $raw as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$color    = isset( $row['color'] ) ? sanitize_key( $row['color'] ) : '';
		$size     = isset( $row['size'] ) ? sanitize_key( $row['size'] ) : '';
		$quantity = isset( $row['quantity'] ) ? absint( $row['quantity'] ) : 0;
		if ( '' === $color && '' === $size ) {
			continue; // пустая строка формы
		}
		if ( $quantity < 1 ) {
			continue;
		}
		$key = $color . '|' . $size;
		if ( ! isset( $merged[ $key ] ) ) {
			$merged[ $key ] = array( 'color' => $color, 'size' => $size, 'quantity' => 0 );
		}
		$merged[ $key ]['quantity'] += $quantity;
	}
	return array_values( $merged );
}

/** Сколько мантий в заказе всего. */
function gr_items_total_quantity( $items ) {
	$total = 0;
	foreach ( $items as $item ) {
		$total += (int) ( is_array( $item ) ? $item['quantity'] : $item->quantity );
	}
	return $total;
}

/**
 * Считает стоимость заказа (за аренду, не за сутки):
 * - взрослые мантии — по ступени ОБЩЕГО количества (детские тоже считаются);
 * - детские — всегда по своей цене;
 * - от N мантий одна бесплатно (вычитается взрослая, если есть, иначе детская).
 * Доставка (от 100 шт.) бесплатная. Параметры дат и доставки — для совместимости вызовов.
 *
 * @return array quantity, kids, unit_price, kids_price, free_gowns, discount, rental, delivery_fee, pickup_fee, total, free_delivery
 */
function gr_calc_totals( $items, $date_from = '', $date_to = '', $delivery = false, $pickup = false ) {
	$qty  = gr_items_total_quantity( $items );
	$kids = 0;
	foreach ( $items as $item ) {
		$item  = (array) $item;
		$kids += 'kids' === $item['size'] ? (int) $item['quantity'] : 0;
	}
	$unit     = gr_unit_price( $qty );
	$kids_p   = gr_kids_price();
	$freeN    = $qty >= gr_free_gown_from() ? 1 : 0;
	$discount = $freeN ? ( $qty > $kids ? $unit : $kids_p ) : 0;
	$rent     = $unit * ( $qty - $kids ) + $kids_p * $kids - $discount;

	return array(
		'quantity'      => $qty,
		'kids'          => $kids,
		'unit_price'    => $unit,
		'kids_price'    => $kids_p,
		'free_gowns'    => $freeN,
		'discount'      => $discount,
		'rental'        => $rent,
		'delivery_fee'  => 0,
		'pickup_fee'    => 0,
		'total'         => $rent,
		'free_delivery' => $qty >= gr_free_delivery_from(),
	);
}

/** Строки цены для квитанции и писем: «12 × 2 000 ₸», «2 детских × 2 000 ₸». */
function gr_price_lines( $totals ) {
	$lines  = array();
	$adults = $totals['quantity'] - $totals['kids'];
	if ( $adults ) {
		$lines[] = $adults . ' × ' . gr_money( $totals['unit_price'] );
	}
	if ( $totals['kids'] ) {
		$lines[] = $totals['kids'] . ' детск. × ' . gr_money( $totals['kids_price'] );
	}
	return $lines;
}

/** Конец аренды: время начала + 3 часа («11:30» → «14:30»). */
function gr_end_time( $time ) {
	return gmdate( 'H:i', strtotime( '1970-01-01 ' . $time . ' UTC' ) + gr_rental_hours() * HOUR_IN_SECONDS );
}

/* -------------------------------------------------------------------------
 * Даты, время, деньги
 * ---------------------------------------------------------------------- */

/** Проверка, что строка — настоящая дата в формате ГГГГ-ММ-ДД. */
function gr_is_valid_date( $date ) {
	$dt = DateTime::createFromFormat( 'Y-m-d', (string) $date );
	return $dt && $dt->format( 'Y-m-d' ) === $date;
}

/** Проверка времени в формате ЧЧ:ММ (24 часа). */
function gr_is_valid_time( $time ) {
	return (bool) preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', (string) $time );
}

/** Число дней аренды, включая день выдачи и день возврата. */
function gr_count_days( $from, $to ) {
	return (int) round( ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS ) + 1;
}

/** Дата для людей: 2026-05-25 → 25.05.2026 */
function gr_format_date( $date ) {
	return $date ? date_i18n( 'd.m.Y', strtotime( $date ) ) : '—';
}

/** Сумма для людей: 18000 → «18 000 ₸» */
function gr_money( $amount ) {
	return number_format( (int) $amount, 0, ',', ' ' ) . ' ₸';
}

/** Позиция для людей: «Бордовый, Детский × 2» */
function gr_item_label( $item ) {
	$item = (array) $item;
	return gr_label( gr_colors(), $item['color'] ) . ', ' . gr_label( gr_sizes(), $item['size'] ) . ' × ' . (int) $item['quantity'];
}
