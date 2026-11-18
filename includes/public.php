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
	wp_register_style( 'gr-catalog', GR_URL . 'public/booking.css', array( 'gr-fonts' ), GR_VERSION );
	wp_register_script( 'gr-catalog', GR_URL . 'public/booking.js', array(), GR_VERSION, true );

	wp_localize_script(
		'gr-catalog',
		'grBooking',
		array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'gr_public' ),
			'pricePerDay'  => gr_price_per_day(),
			'deliveryFee'  => gr_delivery_fee(),
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
 * Проверка дат для клиентов сайта (строже, чем в админке):
 * нельзя бронировать на прошедшие даты.
 */
function gr_public_date_error( $from, $to ) {
	if ( ! gr_is_valid_date( $from ) || ! gr_is_valid_date( $to ) ) {
		return 'Укажите даты аренды.';
	}
	if ( $from < current_time( 'Y-m-d' ) ) {
		return 'Нельзя забронировать на прошедшую дату.';
	}
	if ( $to < $from ) {
		return 'Дата возврата не может быть раньше даты получения.';
	}
	if ( gr_count_days( $from, $to ) > gr_max_rental_days() ) {
		return 'Максимальный срок аренды — ' . gr_max_rental_days() . ' дней.';
	}
	return '';
}

/* -------------------------------------------------------------------------
 * AJAX: наличие и стоимость
 * ---------------------------------------------------------------------- */

function gr_ajax_quote() {
	check_ajax_referer( 'gr_public', 'nonce' );

	$from     = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
	$to       = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';
	$items    = gr_normalize_items( isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : '' );
	$delivery = ! empty( $_POST['delivery'] );

	$date_error = gr_public_date_error( $from, $to );
	if ( $date_error ) {
		wp_send_json_error( array( 'message' => $date_error ) );
	}

	// Клиенту не показываем, СКОЛЬКО свободно: только есть / нет,
	// и какие из выбранных позиций в нужном количестве не набираются.
	$matrix = gr_get_availability_matrix( $from, $to );
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
			'totals' => gr_calc_totals( $items, $from, $to, $delivery, $delivery ),
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

	$from = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
	$to   = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';

	$date_error = gr_public_date_error( $from, $to );
	if ( $date_error ) {
		wp_send_json_error( array( 'errors' => array( $date_error ) ) );
	}

	// Статус и источник задаёт сервер, а не браузер.
	$input           = wp_unslash( $_POST );
	$input['status'] = 'new';
	$input['source'] = 'site';
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

	$lines = array();
	foreach ( $booking->items as $item ) {
		$lines[] = gr_item_label( $item );
	}

	wp_send_json_success(
		array(
			'id'      => (int) $booking->id,
			'message' => sprintf(
				'Номер вашей брони: %d. %s; с %s по %s. Итого: %s. Мы свяжемся с вами по номеру %s для подтверждения.',
				$booking->id,
				implode( '; ', $lines ),
				gr_format_date( $booking->date_from ),
				gr_format_date( $booking->date_to ),
				gr_money( $booking->total_price ),
				$booking->phone
			),
		)
	);
}
add_action( 'wp_ajax_gr_submit_booking', 'gr_ajax_submit_booking' );
add_action( 'wp_ajax_nopriv_gr_submit_booking', 'gr_ajax_submit_booking' );
