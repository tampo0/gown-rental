<?php
/**
 * Письма о новых бронях: администратору и клиенту.
 * Локально все письма видны в LocalWP → Tools → Mailpit.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Текст с деталями брони — общий для обоих писем. */
function gr_booking_summary( $booking ) {
	$lines = array( 'Мантии:' );
	foreach ( $booking->items as $item ) {
		$lines[] = '  • ' . gr_item_label( $item );
	}
	$lines[] = 'Всего мантий: ' . gr_items_total_quantity( $booking->items );
	$lines[] = $booking->time_from
		? 'Когда: ' . gr_format_date( $booking->date_from ) . ', ' . $booking->time_from . '–' . gr_end_time( $booking->time_from )
		: 'Даты: ' . gr_format_date( $booking->date_from ) . ' – ' . gr_format_date( $booking->date_to );
	$lines[] = '';
	$lines[] = gr_delivery_summary( $booking );
	$lines[] = '';
	$totals  = gr_calc_totals( $booking->items );
	$lines[] = 'Цена: ' . implode( ' + ', gr_price_lines( $totals ) ) . ( $totals['free_gowns'] ? ' (одна для организатора бесплатно)' : '' );
	$lines[] = 'Итого: ' . gr_money( $booking->total_price );
	return implode( "\n", $lines );
}

/** Доставка и забор одним блоком текста. */
function gr_delivery_summary( $booking ) {
	if ( ! $booking->delivery && ! $booking->pickup ) {
		return 'Получение: самовывоз — ' . gr_pickup_address() . "\nЗалог: оригинал удостоверения личности (или фото удостоверения + 10 000 ₸).";
	}
	$lines = array( 'Адрес: ' . $booking->address );
	if ( $booking->delivery ) {
		$lines[] = 'Доставка: ' . gr_format_date( $booking->date_from ) . ' к ' . ( $booking->time_from ?: $booking->delivery_time );
	} else {
		$lines[] = 'Получение: самовывоз';
	}
	if ( $booking->pickup ) {
		$lines[] = 'Забрать мантии: ' . gr_format_date( $booking->date_to )
			. ( $booking->pickup_time ? ' в ' . $booking->pickup_time : ', время согласуем с клиентом' );
	} else {
		$lines[] = 'Возврат: клиент привозит сам';
	}
	return implode( "\n", $lines );
}

/** Сообщение заявки, которое клиент отправляет нам в WhatsApp после брони. */
function gr_booking_whatsapp_text( $booking ) {
	$totals = gr_calc_totals( $booking->items );
	$lines  = array( 'Здравствуйте! Бронь №' . $booking->id . ' с сайта.' );
	$lines[] = 'Когда: ' . gr_format_date( $booking->date_from ) . ', ' . $booking->time_from . '–' . gr_end_time( $booking->time_from );
	foreach ( $booking->items as $item ) {
		$lines[] = '• ' . gr_item_label( $item );
	}
	$lines[] = 'Итого: ' . gr_money( $booking->total_price ) . ( $totals['free_gowns'] ? ' (одна мантия бесплатно)' : '' );
	$lines[] = ( $booking->delivery ? 'Доставка: ' . $booking->address : 'Самовывоз' );
	$lines[] = 'Имя: ' . $booking->customer_name;
	$lines[] = 'Подтвердите, пожалуйста, бронь и как оплатить.';
	return implode( "\n", $lines );
}

function gr_send_new_booking_emails( $booking ) {
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

	// Администратору.
	$admin_link = admin_url( 'admin.php?page=gr-bookings&action=edit&id=' . (int) $booking->id );
	$admin_body = "Новая бронь №{$booking->id} с сайта\n\n"
		. 'Клиент: ' . $booking->customer_name . "\n"
		. 'Телефон: ' . $booking->phone . "\n"
		. 'WhatsApp: https://wa.me/' . preg_replace( '/\D/', '', $booking->phone ) . "\n"
		. ( $booking->email ? 'Email: ' . $booking->email . "\n" : '' )
		. "\n" . gr_booking_summary( $booking ) . "\n"
		. ( $booking->note ? "\nКомментарий: " . $booking->note . "\n" : '' )
		. "\nОткрыть бронь: " . $admin_link;

	$headers = array();
	if ( $booking->email ) {
		$headers[] = 'Reply-To: ' . $booking->customer_name . ' <' . $booking->email . '>';
	}
	wp_mail( gr_contact_email(), "[{$site}] Новая бронь №{$booking->id}", $admin_body, $headers );

	// Клиенту — только если указал email.
	if ( $booking->email ) {
		$client_body = "Здравствуйте, {$booking->customer_name}!\n\n"
			. "Мы получили вашу заявку №{$booking->id}.\n\n"
			. gr_booking_summary( $booking ) . "\n\n"
			. "Мы свяжемся с вами по номеру {$booking->phone} для подтверждения.\n\n"
			. $site;
		wp_mail( $booking->email, "Заявка №{$booking->id} принята — {$site}", $client_body );
	}
}
