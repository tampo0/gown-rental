<?php
/**
 * Автотесты плагина Gown Rental.
 *
 * Запуск (из папки сайта app/public):
 *   php wp-content/plugins/gown-rental/tests/run-tests.php
 *
 * Тесты работают с настоящей базой сайта, но:
 *  - берут даты в 2030 году, где нет реальных броней;
 *  - на время теста ставят известный остаток на складе для «бордовый, стандарт»;
 *  - в конце удаляют всё, что создали, и возвращают склад как было.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( "Запускайте из командной строки.\n" );
}

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'gown-rental.local';
require dirname( __DIR__, 4 ) . '/wp-load.php';

// Админские файлы WordPress подключает только в админке — для тестов AJAX подключаем сами.
require_once GR_PATH . 'admin/menu.php';
require_once GR_PATH . 'admin/bookings-page.php';

/* -------------------------------------------------------------------------
 * Мини-набор для проверок
 * ---------------------------------------------------------------------- */

$GLOBALS['gr_t'] = array( 'pass' => 0, 'fail' => 0 );

function check( $name, $condition, $details = '' ) {
	if ( $condition ) {
		$GLOBALS['gr_t']['pass']++;
		echo "  ✓ {$name}\n";
	} else {
		$GLOBALS['gr_t']['fail']++;
		echo "  ✗ {$name}" . ( $details ? "  → {$details}" : '' ) . "\n";
	}
}

function section( $title ) {
	echo "\n{$title}\n";
}

/** Создаёт бронь и запоминает её номер, чтобы потом удалить. */
function make_booking( $fields ) {
	$data = array_merge(
		array(
			'customer_name' => 'Автотест',
			'phone'         => '+7 700 000 00 00',
			'status'        => 'new',
			'source'        => 'site',
		),
		$fields
	);
	$result = gr_save_booking( wp_slash( $data ) );
	if ( ! is_wp_error( $result ) ) {
		$GLOBALS['gr_created'][] = $result;
	}
	return $result;
}

function errors_of( $result ) {
	if ( ! is_wp_error( $result ) ) {
		return '';
	}
	return implode( ' | ', $result->get_error_messages() );
}

/** Вызов AJAX-обработчика: перехватываем wp_send_json и возвращаем ответ как массив. */
function call_ajax( $callback, $post ) {
	$_POST    = $post;
	$_REQUEST = $post;
	add_filter( 'wp_doing_ajax', '__return_true' );
	$stop = function () {
		return function () {
			throw new RuntimeException( 'ajax-end' );
		};
	};
	add_filter( 'wp_die_ajax_handler', $stop );
	ob_start();
	try {
		call_user_func( $callback );
	} catch ( RuntimeException $e ) {
		// Ответ уже напечатан в буфер.
	}
	$out = ob_get_clean();
	remove_filter( 'wp_die_ajax_handler', $stop );
	remove_filter( 'wp_doing_ajax', '__return_true' );
	return json_decode( $out, true );
}

/* -------------------------------------------------------------------------
 * Подготовка
 * ---------------------------------------------------------------------- */

$GLOBALS['gr_created'] = array();
$color                 = 'red';
$size                  = 'standard';
$stock_before          = gr_get_stock( $color, $size );
gr_set_stock( $color, $size, 3 );

$item = function ( $qty, $c = 'red', $s = 'standard' ) {
	return array( array( 'color' => $c, 'size' => $s, 'quantity' => $qty ) );
};

try {

	section( '1. Цены' );
	$expect = array( 1 => 5000, 2 => 5000, 3 => 3000, 4 => 3000, 5 => 2500, 9 => 2500, 10 => 2000, 19 => 2000, 20 => 2000 );
	$ok     = true;
	foreach ( $expect as $q => $price ) {
		$ok = $ok && gr_unit_price( $q ) === $price;
	}
	check( 'Цена за мантию по ступеням: 1–2 → 5000, 3–4 → 3000, 5–9 → 2500, от 10 → 2000', $ok );

	$t = gr_calc_totals( $item( 4 ) );
	check( '4 мантии = 4 × 3 000 = 12 000 ₸', 12000 === $t['total'], 'получили ' . $t['total'] );
	$t = gr_calc_totals( $item( 19 ) );
	check( '19 мантий — без бесплатной: 38 000 ₸', 38000 === $t['total'] && 0 === $t['free_gowns'] );
	$t = gr_calc_totals( $item( 20 ) );
	check( 'От 20 мантий одна бесплатно: 20 шт. = 19 × 2 000 = 38 000 ₸', 38000 === $t['total'] && 1 === $t['free_gowns'] );
	check( 'Доставка всегда 0 ₸', 0 === $t['delivery_fee'] && 0 === $t['pickup_fee'] );
	check( 'Аренда 3 часа: 11:30 → 14:30', '14:30' === gr_end_time( '11:30' ) );

	section( '2. Наличие по дням — другие города (на складе 3 шт.)' );
	check( 'Пока броней нет — свободно 3', 3 === gr_get_available( $color, $size, '2030-03-01', '2030-03-05' ) );

	$a = make_booking( array( 'date_from' => '2030-03-01', 'date_to' => '2030-03-03', 'items' => $item( 2 ) ) );
	check( 'Бронь A: 2 шт. на 1–3 марта сохранилась', ! is_wp_error( $a ), errors_of( $a ) );
	check( '1–3 марта свободна 1 шт.', 1 === gr_get_available( $color, $size, '2030-03-01', '2030-03-03' ) );
	check( 'День возврата занят: 3 марта свободна 1 шт.', 1 === gr_get_available( $color, $size, '2030-03-03', '2030-03-03' ) );
	check( 'Со следующего дня снова свободно: 4 марта — 3 шт.', 3 === gr_get_available( $color, $size, '2030-03-04', '2030-03-04' ) );

	$b = make_booking( array( 'date_from' => '2030-03-03', 'date_to' => '2030-03-05', 'items' => $item( 2 ) ) );
	check( 'Бронь B: 2 шт. на 3–5 марта отклонена (3 марта было бы 4 > 3)', is_wp_error( $b ) );

	$c = make_booking( array( 'date_from' => '2030-03-03', 'date_to' => '2030-03-05', 'items' => $item( 1 ) ) );
	check( 'Бронь C: 1 шт. на 3–5 марта принята (ровно 3 в пике)', ! is_wp_error( $c ), errors_of( $c ) );

	$a_again = gr_save_booking(
		wp_slash(
			array(
				'customer_name' => 'Автотест',
				'phone'         => '+7 700 000 00 00',
				'status'        => 'new',
				'source'        => 'site',
				'date_from'     => '2030-03-01',
				'date_to'       => '2030-03-03',
				'items'         => $item( 2 ),
			)
		),
		$a
	);
	check( 'Редактирование A не считает саму A занятой', ! is_wp_error( $a_again ), errors_of( $a_again ) );

	section( '2б. Аренда на 3 часа (20 марта, на складе 3 шт.)' );
	$h1 = make_booking( array( 'date_from' => '2030-03-20', 'time_from' => '11:00', 'items' => $item( 2 ) ) );
	check( 'Бронь 11:00–14:00 на 2 шт. принята', ! is_wp_error( $h1 ), errors_of( $h1 ) );
	check( 'В 12:30 (пересекается) свободна 1 шт.', 1 === gr_get_available( $color, $size, '2030-03-20', '2030-03-20', 0, '12:30' ) );
	check( 'С 14:00 мантии снова свободны — 3 шт.', 3 === gr_get_available( $color, $size, '2030-03-20', '2030-03-20', 0, '14:00' ) );

	$h2 = make_booking( array( 'date_from' => '2030-03-20', 'time_from' => '13:30', 'items' => $item( 2 ) ) );
	check( 'Бронь 13:30 на 2 шт. отклонена (в 13:30–14:00 было бы 4 > 3)', is_wp_error( $h2 ) );

	$h3 = make_booking( array( 'date_from' => '2030-03-20', 'time_from' => '14:00', 'items' => $item( 3 ) ) );
	check( 'Бронь 14:00 на все 3 шт. принята — сразу после первой', ! is_wp_error( $h3 ), errors_of( $h3 ) );

	$day = make_booking( array( 'date_from' => '2030-03-20', 'date_to' => '2030-03-20', 'items' => $item( 1 ) ) );
	check( 'Бронь на весь день (другой город) отклонена: в 14:00 было бы 4', is_wp_error( $day ) );

	$saved = gr_get_booking( $h1 );
	check( 'У брони со временем дата возврата = дата получения', $saved && $saved->date_to === $saved->date_from && '11:00' === $saved->time_from );

	section( '3. Статусы' );
	$r = gr_set_booking_status( $a, 'cancelled' );
	check( 'Отмена A прошла', ! is_wp_error( $r ) );
	check( 'После отмены A 3 марта свободно 2 шт.', 2 === gr_get_available( $color, $size, '2030-03-03', '2030-03-03' ) );

	$d = make_booking( array( 'date_from' => '2030-03-03', 'date_to' => '2030-03-03', 'items' => $item( 2 ) ) );
	check( 'Бронь D: 2 шт. на 3 марта принята, пока A отменена', ! is_wp_error( $d ), errors_of( $d ) );

	$r = gr_set_booking_status( $a, 'confirmed' );
	check( 'Вернуть A в работу нельзя — мантий уже не хватает', is_wp_error( $r ) );

	$r = gr_set_booking_status( $d, 'returned' );
	check( 'D отмечена «Возвращена»', ! is_wp_error( $r ) );
	$r = gr_set_booking_status( $a, 'confirmed' );
	check( 'Теперь A можно снова подтвердить', ! is_wp_error( $r ), is_wp_error( $r ) ? $r->get_error_message() : '' );

	section( '4. Проверка данных брони' );
	$before = count( gr_get_bookings() );
	$mixed  = make_booking(
		array(
			'date_from' => '2030-03-02',
			'date_to'   => '2030-03-02',
			'items'     => array(
				array( 'color' => 'blue', 'size' => 'standard', 'quantity' => 1 ),
				array( 'color' => 'red', 'size' => 'long', 'quantity' => 5 ),
			),
		)
	);
	check( 'Если одной позиции не хватает — вся бронь отклоняется', is_wp_error( $mixed ) );
	check( '…и ничего не сохраняется', count( gr_get_bookings() ) === $before );

	$r = make_booking( array( 'date_from' => '2030-03-10', 'date_to' => '2030-03-10', 'items' => $item( 1, 'purple', 'kids' ) ) );
	check( 'Фиолетовый детский — отклонён (такого размера нет)', is_wp_error( $r ) );

	$r = make_booking( array( 'date_from' => '2030-03-10', 'date_to' => '2030-03-09', 'items' => $item( 1 ) ) );
	check( 'Дата возврата раньше получения — ошибка', is_wp_error( $r ) );

	$r = make_booking( array( 'date_from' => '2030-03-10', 'date_to' => '2030-03-10', 'items' => $item( 1 ), 'delivery' => 1, 'address' => 'ул. Тестовая 1' ) );
	check( 'Доставка меньше чем на 100 мантий — ошибка', is_wp_error( $r ) && false !== strpos( errors_of( $r ), '100' ) );

	$r = make_booking( array( 'date_from' => '2030-03-10', 'time_from' => '25:99', 'items' => $item( 1 ) ) );
	check( 'Неверное время начала — ошибка', is_wp_error( $r ) );

	$r = make_booking( array( 'date_from' => '2030-03-10', 'date_to' => '2030-03-10', 'items' => $item( 1 ), 'phone' => '123' ) );
	check( 'Короткий телефон — ошибка', is_wp_error( $r ) );

	$r = make_booking( array( 'date_from' => '2030-03-10', 'date_to' => '2030-03-10', 'items' => array() ) );
	check( 'Бронь без мантий — ошибка', is_wp_error( $r ) );

	section( '5. Сайт не раскрывает количество свободных мантий' );
	$res = call_ajax(
		'gr_ajax_quote',
		array(
			'nonce'     => wp_create_nonce( 'gr_public' ),
			'date'      => '2030-03-03',
			'time'      => '12:00',
			'items'     => wp_json_encode( $item( 99 ) ),
		)
	);
	$val = $res['data']['matrix']['red']['standard'] ?? null;
	check( 'В ответе только да/нет, без чисел', is_bool( $val ), 'получили ' . var_export( $val, true ) );
	check( 'Нехватка помечена как «short» без количества', in_array( 'red|standard', $res['data']['short'] ?? array(), true ) );
	check( 'У фиолетового нет детского размера в ответе', ! isset( $res['data']['matrix']['purple']['kids'] ) );
	check( '«Великан» только у бордового и изумруда', isset( $res['data']['matrix']['red']['giant'], $res['data']['matrix']['green']['giant'] ) && ! isset( $res['data']['matrix']['blue']['giant'] ) );
	$res = call_ajax( 'gr_ajax_quote', array( 'nonce' => wp_create_nonce( 'gr_public' ), 'date' => '2030-03-03', 'time' => '15:00', 'items' => '[]' ) );
	check( 'Начало позже 14:00 сайт не принимает', isset( $res['success'] ) && false === $res['success'] );

	section( '5б. Согласие на обработку персональных данных' );
	$form = array(
		'nonce'         => wp_create_nonce( 'gr_public' ),
		'date'          => '2030-04-01',
		'time'          => '11:00',
		'customer_name' => 'Автотест',
		'phone'         => '+7 700 000 00 00',
		'delivery'      => '0',
		'items'         => wp_json_encode( $item( 1 ) ),
	);
	$res = call_ajax( 'gr_ajax_submit_booking', $form );
	check( 'Без галочки согласия бронь с сайта не принимается', isset( $res['success'] ) && false === $res['success'] && false !== strpos( implode( ' ', $res['data']['errors'] ?? array() ), 'согласие' ) );
	$res = call_ajax( 'gr_ajax_submit_booking', $form + array( 'consent' => '1' ) );
	check( 'С галочкой — бронь принята', ! empty( $res['success'] ), wp_json_encode( $res ) );
	if ( ! empty( $res['data']['id'] ) ) {
		$GLOBALS['gr_created'][] = (int) $res['data']['id'];
	}

	section( '5в. Почта для заявок' );
	$mail = new class() { public function name() { return 'mail'; } };
	$mc   = apply_filters( 'wpcf7_mail_components', array( 'recipient' => 'old@example.com' ), null, $mail );
	check( 'Письмо формы «Напишите нам» уходит на email из настроек', gr_contact_email() === $mc['recipient'] );
	$auto = new class() { public function name() { return 'mail_2'; } };
	$mc   = apply_filters( 'wpcf7_mail_components', array( 'recipient' => 'client@example.com' ), null, $auto );
	check( 'Автоответ клиенту не перенаправляется', 'client@example.com' === $mc['recipient'] );

	section( '6. Смена статуса из списка броней (AJAX админки)' );
	wp_set_current_user( 0 );
	$res = call_ajax( 'gr_ajax_set_status', array( 'nonce' => wp_create_nonce( 'gr_admin' ), 'id' => $c, 'status' => 'confirmed' ) );
	check( 'Гость не может менять статус', isset( $res['success'] ) && false === $res['success'] );

	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	wp_set_current_user( (int) $admins[0] );
	$res = call_ajax( 'gr_ajax_set_status', array( 'nonce' => 'wrong', 'id' => $c, 'status' => 'confirmed' ) );
	check( 'Без правильного nonce — отказ', null === $res || ( isset( $res['success'] ) && false === $res['success'] ) );

	$res = call_ajax( 'gr_ajax_set_status', array( 'nonce' => wp_create_nonce( 'gr_admin' ), 'id' => $c, 'status' => 'confirmed' ) );
	check( 'Администратор меняет статус', ! empty( $res['success'] ) );
	check( 'Статус в базе обновился', 'confirmed' === gr_get_booking( $c )->status );

	$res = call_ajax( 'gr_ajax_set_status', array( 'nonce' => wp_create_nonce( 'gr_admin' ), 'id' => $c, 'status' => 'nonsense' ) );
	check( 'Неизвестный статус — отказ', isset( $res['success'] ) && false === $res['success'] );
	wp_set_current_user( 0 );

	section( '7. Удаление' );
	gr_delete_booking( $c );
	check( 'Бронь удалена вместе с позициями', null === gr_get_booking( $c ) && array() === gr_get_booking_items( $c ) );
	$GLOBALS['gr_created'] = array_diff( $GLOBALS['gr_created'], array( $c ) );

} finally {
	foreach ( $GLOBALS['gr_created'] as $id ) {
		gr_delete_booking( $id );
	}
	gr_set_stock( $color, $size, $stock_before );
}

$t = $GLOBALS['gr_t'];
echo "\nИтого: {$t['pass']} пройдено, {$t['fail']} не пройдено. Тестовые брони удалены, склад восстановлен.\n";
exit( $t['fail'] ? 1 : 0 );
