<?php
/**
 * Каталог с бронированием на сайте.
 *
 * Шорткод [gown_catalog] выводит каталог: даты → карточки цветов (фото) →
 * клик по цвету открывает размеры → клик по размеру открывает количество.
 * Клиент может выбрать сколько угодно цветов и размеров в одной брони.
 *
 * AJAX:
 *   gr_quote          — свободные мантии на даты + расчёт стоимости;
 *   gr_submit_booking — создать бронь со статусом «Новая».
 * Сервер всегда перепроверяет всё сам — данным из браузера не доверяем.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Подключение JS и CSS
 * ---------------------------------------------------------------------- */

function gr_register_assets() {
	wp_register_style( 'gr-fonts', 'https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600&family=Unbounded:wght@500;700&display=swap', array(), null );
	wp_register_style( 'gr-ladder', GR_URL . 'public/ladder.css', array(), GR_VERSION );
	wp_register_style( 'gr-catalog', GR_URL . 'public/booking.css', array( 'gr-fonts', 'gr-ladder' ), GR_VERSION );
	wp_register_script( 'gr-catalog', GR_URL . 'public/booking.js', array(), GR_VERSION, true );

	wp_localize_script(
		'gr-catalog',
		'grBooking',
		array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'gr_public' ),
			'tiers'        => gr_price_tiers(),
			'freeGownFrom' => gr_free_gown_from(),
			'kidsPrice'    => gr_kids_price(),
			'freeFrom'     => gr_free_delivery_from(),
			'today'        => current_time( 'Y-m-d' ),
			'colors'       => gr_colors(),
			'sizes'        => gr_sizes(),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'gr_register_assets' );

/** Рисунок мантии нужного цвета — если фото ещё не загружено. */
function gr_gown_placeholder_svg( $hex ) {
	return '<svg class="gr-gown-svg" viewBox="0 0 120 140" aria-hidden="true" style="color:' . esc_attr( $hex ) . '">'
		. '<path fill="currentColor" d="M60 12 42 18 18 44l10 10 10-10-5 82h54l-5-82 10 10 10-10-24-26z"/>'
		. '<path fill="none" stroke="rgba(255,255,255,.55)" stroke-width="3" d="M48 17l12 22 12-22"/>'
		. '<path fill="#222" d="M60 0 30 10l30 10 30-10z"/><path stroke="#d4a017" stroke-width="2" d="M86 11v14"/>'
		. '</svg>';
}

/* -------------------------------------------------------------------------
 * Шорткод [gown_catalog] (старый [gown_booking] показывает то же самое)
 * ---------------------------------------------------------------------- */

// Разметка каталога — в includes/catalog-template.php.
require_once GR_PATH . 'includes/catalog-template.php';

add_shortcode( 'gown_catalog', 'gr_catalog_shortcode' );
add_shortcode( 'gown_booking', 'gr_catalog_shortcode' );

/* -------------------------------------------------------------------------
 * Общие проверки для сайта
 * ---------------------------------------------------------------------- */

/**
 * Проверка даты и времени для клиентов сайта: аренда в Шымкенте — 3 часа,
 * начало с 9:30 до 15:00 (вернуть до 18:00), не в прошлом.
 */
function gr_public_slot_error( $date, $time ) {
	if ( ! gr_is_valid_date( $date ) ) {
		return 'Укажите дату аренды.';
	}
	if ( ! in_array( $time, gr_start_times(), true ) ) {
		return 'Выберите время начала: с ' . gr_start_window() . '.';
	}
	if ( $date . ' ' . $time <= current_time( 'Y-m-d H:i' ) ) {
		return 'Это время уже прошло — выберите другое.';
	}
	return '';
}

/** Дата и время из запроса сайта. */
function gr_public_slot() {
	return array(
		isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification -- nonce проверен в обработчике
		isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
	);
}

/* -------------------------------------------------------------------------
 * AJAX: наличие и стоимость
 * ---------------------------------------------------------------------- */

function gr_ajax_quote() {
	check_ajax_referer( 'gr_public', 'nonce' );

	list( $date, $time ) = gr_public_slot();
	$items               = gr_normalize_items( isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : '' );

	$slot_error = gr_public_slot_error( $date, $time );
	if ( $slot_error ) {
		wp_send_json_error( array( 'message' => $slot_error ) );
	}

	// Клиенту не показываем, СКОЛЬКО свободно: только есть / нет,
	// и какие из выбранных позиций в нужном количестве не набираются.
	$matrix = gr_get_availability_matrix( $date, $date, 0, $time );
	$public = array();
	foreach ( $matrix as $color => $sizes ) {
		foreach ( $sizes as $size => $free ) {
			$public[ $color ][ $size ] = $free > 0;
		}
	}
	$short = array();
	foreach ( $items as $item ) {
		$free = isset( $matrix[ $item['color'] ][ $item['size'] ] ) ? $matrix[ $item['color'] ][ $item['size'] ] : 0;
		if ( $item['quantity'] > $free ) {
			$short[] = $item['color'] . '|' . $item['size'];
		}
	}

	wp_send_json_success(
		array(
			'matrix' => $public,
			'short'  => $short,
			'totals' => gr_calc_totals( $items ),
		)
	);
}
add_action( 'wp_ajax_gr_quote', 'gr_ajax_quote' );        // для вошедших
add_action( 'wp_ajax_nopriv_gr_quote', 'gr_ajax_quote' ); // для гостей

/* -------------------------------------------------------------------------
 * AJAX: отправка брони
 * ---------------------------------------------------------------------- */

function gr_ajax_submit_booking() {
	check_ajax_referer( 'gr_public', 'nonce' );

	// Спам-бот заполнил скрытое поле — делаем вид, что всё хорошо, но ничего не сохраняем.
	if ( ! empty( $_POST['website'] ) ) {
		wp_send_json_success( array( 'message' => 'Спасибо! Заявка отправлена.' ) );
	}

	list( $date, $time ) = gr_public_slot();

	$slot_error = gr_public_slot_error( $date, $time );
	if ( $slot_error ) {
		wp_send_json_error( array( 'errors' => array( $slot_error ) ) );
	}

	// Закон РК «О персональных данных и их защите»: нужно согласие клиента.
	if ( empty( $_POST['consent'] ) ) {
		wp_send_json_error( array( 'errors' => array( 'Подтвердите согласие на обработку персональных данных.' ) ) );
	}

	// Статус и источник задаёт сервер, а не браузер.
	$input           = wp_unslash( $_POST );
	$input['status']    = 'new';
	$input['source']    = 'site';
	$input['date_from'] = $date;
	$input['date_to']   = $date;
	$input['time_from'] = $time;
	// С сайта: доставка = привезём и заберём сами; самовывоз = без адреса.
	$input['pickup']      = ! empty( $input['delivery'] ) ? 1 : 0;
	$input['pickup_time'] = '';

	// gr_save_booking ждёт «сырые» данные со слешами, как в $_POST.
	$result = gr_save_booking( wp_slash( $input ) );

	if ( is_wp_error( $result ) ) {
		// Не раскрываем клиенту, сколько мантий свободно.
		$errors = array_map(
			function ( $message ) {
				return preg_replace( '/ Свободно: \d+, нужно: \d+\./u', ' Столько мантий на эти даты нет — уменьшите количество.', $message );
			},
			$result->get_error_messages()
		);
		wp_send_json_error( array( 'errors' => $errors ) );
	}

	$booking = gr_get_booking( $result );
	gr_send_new_booking_emails( $booking );

	// Данные для квитанции на странице — разметку строит booking.js.
	$totals = gr_calc_totals( $booking->items );
	$items  = array();
	foreach ( $booking->items as $item ) {
		$items[] = array(
			'color'    => gr_label( gr_colors(), $item->color ),
			'size'     => gr_label( gr_sizes(), $item->size ),
			'quantity' => (int) $item->quantity,
		);
	}

	wp_send_json_success(
		array(
			'id'         => (int) $booking->id,
			'date'       => date_i18n( 'j F Y, l', strtotime( $booking->date_from ) ),
			'time'       => $booking->time_from . '–' . gr_end_time( $booking->time_from ),
			'items'      => $items,
			'quantity'   => $totals['quantity'],
			'unit_price' => gr_money( $totals['unit_price'] ),
			'price_lines' => gr_price_lines( $totals ),
			'free_gowns' => $totals['free_gowns'],
			'total'      => gr_money( $booking->total_price ),
			'delivery'   => (bool) $booking->delivery,
			'address'    => $booking->delivery ? $booking->address : gr_pickup_address(),
			'phone'      => $booking->phone,
			'whatsapp'   => gr_whatsapp_url( gr_booking_whatsapp_text( $booking ) ),
		)
	);
}
add_action( 'wp_ajax_gr_submit_booking', 'gr_ajax_submit_booking' );
add_action( 'wp_ajax_nopriv_gr_submit_booking', 'gr_ajax_submit_booking' );
