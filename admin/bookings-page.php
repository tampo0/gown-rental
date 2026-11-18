<?php
/**
 * Страница «Брони»: список, быстрая смена статуса, добавление, редактирование, удаление.
 * Здесь администратор вручную вносит и меняет заявки (например, из WhatsApp).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Точка входа: показываем список или форму. */
function gr_bookings_page() {
	$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';

	if ( 'new' === $action ) {
		gr_render_booking_form( null );
		return;
	}
	if ( 'edit' === $action ) {
		$booking = gr_get_booking( isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0 );
		if ( ! $booking ) {
			echo '<div class="wrap"><h1>Бронь не найдена</h1></div>';
			return;
		}
		gr_render_booking_form( $booking );
		return;
	}
	gr_render_bookings_list();
}

/* -------------------------------------------------------------------------
 * Список броней
 * ---------------------------------------------------------------------- */

function gr_render_bookings_list() {
	$filters = array(
		'status' => isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '',
		'color'  => isset( $_GET['color'] ) ? sanitize_key( $_GET['color'] ) : '',
		'size'   => isset( $_GET['size'] ) ? sanitize_key( $_GET['size'] ) : '',
		'date'   => isset( $_GET['date'] ) && gr_is_valid_date( $_GET['date'] ) ? sanitize_text_field( $_GET['date'] ) : '',
		'search' => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
	);
	$bookings = gr_get_bookings( $filters );
	$new_url  = add_query_arg( array( 'page' => 'gr-bookings', 'action' => 'new' ), admin_url( 'admin.php' ) );
	?>
	<div class="wrap gr-admin">
		<h1 class="wp-heading-inline">Брони</h1>
		<a href="<?php echo esc_url( $new_url ); ?>" class="page-title-action">Добавить бронь</a>
		<hr class="wp-header-end">
		<?php gr_admin_notice(); ?>

		<form method="get" class="gr-filters">
			<input type="hidden" name="page" value="gr-bookings">
			<?php
			gr_select( 'status', array( '' => 'Все статусы' ) + gr_statuses(), $filters['status'] );
			gr_select( 'color', array( '' => 'Все цвета' ) + gr_colors(), $filters['color'] );
			gr_select( 'size', array( '' => 'Все размеры' ) + gr_sizes(), $filters['size'] );
			?>
			<label>Действует на дату:
				<input type="date" name="date" value="<?php echo esc_attr( $filters['date'] ); ?>">
			</label>
			<input type="search" name="s" placeholder="Имя или телефон" value="<?php echo esc_attr( $filters['search'] ); ?>">
			<button class="button">Фильтр</button>
			<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=gr-bookings' ) ); ?>">Сбросить</a>
		</form>

		<table class="widefat striped gr-bookings-table">
			<thead>
				<tr>
					<th>№</th>
					<th>Клиент</th>
					<th>Мантии</th>
					<th>Даты</th>
					<th>Доставка</th>
					<th>Сумма</th>
					<th>Статус</th>
					<th>Комментарий</th>
					<th>Действия</th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $bookings ) : ?>
				<tr><td colspan="9">Броней не найдено.</td></tr>
			<?php endif; ?>

			<?php
			foreach ( $bookings as $b ) :
				$edit_url   = add_query_arg( array( 'page' => 'gr-bookings', 'action' => 'edit', 'id' => $b->id ), admin_url( 'admin.php' ) );
				$delete_url = wp_nonce_url(
					add_query_arg( array( 'action' => 'gr_delete_booking', 'id' => $b->id ), admin_url( 'admin-post.php' ) ),
					'gr_delete_booking_' . $b->id
				);
				$wa_phone   = preg_replace( '/\D/', '', $b->phone );
				?>
				<tr class="gr-row-<?php echo esc_attr( $b->status ); ?>">
					<td><?php echo (int) $b->id; ?></td>
					<td>
						<strong><?php echo esc_html( $b->customer_name ); ?></strong><br>
						<a href="<?php echo esc_url( 'https://wa.me/' . $wa_phone ); ?>" target="_blank" rel="noopener">
							<?php echo esc_html( $b->phone ); ?>
						</a>
						<br><small><?php echo esc_html( gr_label( gr_sources(), $b->source ) ); ?></small>
					</td>
					<td>
						<?php foreach ( $b->items as $item ) : ?>
							<div><?php echo esc_html( gr_item_label( $item ) ); ?></div>
						<?php endforeach; ?>
						<small>всего: <?php echo (int) gr_items_total_quantity( $b->items ); ?></small>
					</td>
					<td><?php echo esc_html( gr_format_date( $b->date_from ) . ' – ' . gr_format_date( $b->date_to ) ); ?></td>
					<td>
						<?php if ( $b->delivery ) : ?>
							<div>🚚 привезти в <?php echo esc_html( $b->delivery_time ); ?></div>
						<?php else : ?>
							<div>Самовывоз</div>
						<?php endif; ?>
						<?php if ( $b->pickup ) : ?>
							<div>↩ забрать <?php echo $b->pickup_time ? 'в ' . esc_html( $b->pickup_time ) : '(время уточнить)'; ?></div>
						<?php endif; ?>
						<?php if ( $b->address ) : ?>
							<small><?php echo esc_html( $b->address ); ?></small>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( gr_money( $b->total_price ) ); ?></td>
					<td>
						<?php // Смена статуса прямо в списке — сохраняется сразу (admin.js). ?>
						<select class="gr-status-select" data-id="<?php echo (int) $b->id; ?>"
							data-prev="<?php echo esc_attr( $b->status ); ?>" aria-label="Статус брони №<?php echo (int) $b->id; ?>">
							<?php foreach ( gr_statuses() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $b->status, $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<span class="gr-status-result" aria-live="polite"></span>
					</td>
					<td><?php echo esc_html( wp_trim_words( (string) $b->note, 12 ) ); ?></td>
					<td>
						<a href="<?php echo esc_url( $edit_url ); ?>">Изменить</a> |
						<a href="<?php echo esc_url( $delete_url ); ?>" class="gr-delete"
							onclick="return confirm('Удалить бронь №<?php echo (int) $b->id; ?>? Это нельзя отменить.');">Удалить</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/** Выпадающий список. $attrs — только строки из нашего кода, не данные пользователя. */
function gr_select( $name, $options, $selected, $attrs = '', $id = null ) {
	$id      = null === $id ? 'gr_' . $name : $id;
	$id_attr = '' === $id ? '' : ' id="' . esc_attr( $id ) . '"';
	printf( '<select name="%s"%s %s>', esc_attr( $name ), $id_attr, $attrs );
	foreach ( $options as $value => $label ) {
		printf(
			'<option value="%s"%s>%s</option>',
			esc_attr( $value ),
			selected( (string) $selected, (string) $value, false ),
			esc_html( $label )
		);
	}
	echo '</select> ';
}

/* -------------------------------------------------------------------------
 * Форма добавления / редактирования
 * ---------------------------------------------------------------------- */

/** Одна строка позиции в форме: цвет, размер, количество, удалить. */
function gr_render_item_row( $index, $item ) {
	$item = (array) $item;
	$name = 'items[' . $index . ']';
	?>
	<div class="gr-item-row">
		<?php
		gr_select( $name . '[color]', array( '' => '— цвет —' ) + gr_colors(), $item['color'], 'aria-label="Цвет"', '' );
		gr_select( $name . '[size]', array( '' => '— размер —' ) + gr_sizes(), $item['size'], 'aria-label="Размер"', '' );
		?>
		<input type="number" name="<?php echo esc_attr( $name ); ?>[quantity]" min="1" max="<?php echo (int) gr_max_item_quantity(); ?>"
			value="<?php echo esc_attr( $item['quantity'] ); ?>" class="small-text" aria-label="Количество"> шт.
		<button type="button" class="button-link gr-remove-item" aria-label="Убрать позицию">✕ убрать</button>
	</div>
	<?php
}

/**
 * @param object|null $booking Бронь для редактирования или null для новой.
 */
function gr_render_booking_form( $booking ) {
	$id = $booking ? (int) $booking->id : 0;

	// Значения по умолчанию для новой брони.
	$data = array(
		'customer_name' => '',
		'phone'         => '',
		'email'         => '',
		'date_from'     => '',
		'date_to'       => '',
		'delivery'      => 0,
		'delivery_time' => '',
		'pickup'        => 0,
		'pickup_time'   => '',
		'address'       => '',
		'source'        => 'whatsapp',
		'status'        => 'confirmed',
		'note'          => '',
		'items'         => array(),
	);
	if ( $booking ) {
		foreach ( array_keys( $data ) as $key ) {
			$data[ $key ] = $booking->$key;
		}
	}

	// Если при сохранении были ошибки — показываем их и введённые значения.
	$errors    = array();
	$transient = 'gr_form_' . get_current_user_id();
	$saved     = get_transient( $transient );
	if ( $saved ) {
		delete_transient( $transient );
		$errors = $saved['errors'];
		$data   = array_merge( $data, $saved['data'] );
	}

	$items = $data['items'] ? $data['items'] : array( array( 'color' => '', 'size' => '', 'quantity' => 1 ) );
	?>
	<div class="wrap gr-admin">
		<h1><?php echo $id ? 'Бронь №' . (int) $id : 'Новая бронь'; ?></h1>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=gr-bookings' ) ); ?>">← Ко всем броням</a></p>

		<?php if ( $errors ) : ?>
			<div class="notice notice-error"><ul>
				<?php foreach ( $errors as $error ) : ?>
					<li><?php echo esc_html( $error ); ?></li>
				<?php endforeach; ?>
			</ul></div>
		<?php endif; ?>

		<?php if ( $booking ) : ?>
			<p class="description">
				Создана: <?php echo esc_html( mysql2date( 'd.m.Y H:i', $booking->created_at ) ); ?>
				<?php if ( $booking->updated_at ) : ?>
					· Изменена: <?php echo esc_html( mysql2date( 'd.m.Y H:i', $booking->updated_at ) ); ?>
				<?php endif; ?>
				· Сумма: <strong><?php echo esc_html( gr_money( $booking->total_price ) ); ?></strong>
				(аренда <?php echo esc_html( gr_money( $booking->rental_price ) ); ?>,
				доставка <?php echo esc_html( gr_money( $booking->delivery_fee ) ); ?>,
				забор <?php echo esc_html( gr_money( $booking->pickup_fee ) ); ?>)
			</p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gr-booking-form">
			<input type="hidden" name="action" value="gr_save_booking">
			<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
			<?php wp_nonce_field( 'gr_save_booking' ); ?>

			<h2>Клиент</h2>
			<table class="form-table">
				<tr>
					<th><label for="gr_customer_name">Имя *</label></th>
					<td><input type="text" id="gr_customer_name" name="customer_name" class="regular-text" required
						value="<?php echo esc_attr( $data['customer_name'] ); ?>"></td>
				</tr>
				<tr>
					<th><label for="gr_phone">Телефон *</label></th>
					<td><input type="tel" id="gr_phone" name="phone" class="regular-text" required
						placeholder="+7 700 123 45 67" value="<?php echo esc_attr( $data['phone'] ); ?>"></td>
				</tr>
				<tr>
					<th><label for="gr_email">Email</label></th>
					<td><input type="email" id="gr_email" name="email" class="regular-text"
						value="<?php echo esc_attr( $data['email'] ); ?>"></td>
				</tr>
			</table>

			<h2>Мантии</h2>
			<div class="gr-items">
				<?php
				foreach ( array_values( $items ) as $i => $item ) {
					gr_render_item_row( $i, $item );
				}
				?>
			</div>
			<p><button type="button" class="button gr-add-item">+ Добавить позицию</button></p>
			<?php // Шаблон новой строки для admin.js (__i__ заменяется на номер). ?>
			<template id="gr-item-template">
				<?php gr_render_item_row( '__i__', array( 'color' => '', 'size' => '', 'quantity' => 1 ) ); ?>
			</template>

			<h2>Даты</h2>
			<table class="form-table">
				<tr>
					<th>Даты *</th>
					<td>
						<label>с <input type="date" name="date_from" required value="<?php echo esc_attr( $data['date_from'] ); ?>"></label>
						<label>по <input type="date" name="date_to" required value="<?php echo esc_attr( $data['date_to'] ); ?>"></label>
						<p class="description">День получения и день возврата (оба дня включительно).
							Со следующего дня после возврата мантии можно выдать снова.</p>
					</td>
				</tr>
			</table>

			<h2>Доставка</h2>
			<p class="description">
				Доставка и забор — по <?php echo esc_html( gr_money( gr_delivery_fee() ) ); ?> каждый,
				бесплатно при заказе от <?php echo (int) gr_free_delivery_from(); ?> мантий. Сумма считается автоматически.
			</p>
			<table class="form-table">
				<tr>
					<th>Доставить</th>
					<td>
						<label><input type="checkbox" name="delivery" value="1" class="gr-toggle" data-target="#gr-delivery-time"
							<?php checked( $data['delivery'] ); ?>> Привезти мантии клиенту (в день получения)</label>
						<p id="gr-delivery-time" <?php echo $data['delivery'] ? '' : 'hidden'; ?>>
							<label>Время доставки * <input type="time" name="delivery_time" value="<?php echo esc_attr( $data['delivery_time'] ); ?>"></label>
						</p>
					</td>
				</tr>
				<tr>
					<th>Забрать</th>
					<td>
						<label><input type="checkbox" name="pickup" value="1" class="gr-toggle" data-target="#gr-pickup-time"
							<?php checked( $data['pickup'] ); ?>> Забрать мантии у клиента (в день возврата; при доставке — бесплатно)</label>
						<p id="gr-pickup-time" <?php echo $data['pickup'] ? '' : 'hidden'; ?>>
							<label>Время <input type="time" name="pickup_time" value="<?php echo esc_attr( $data['pickup_time'] ); ?>"></label>
							<span class="description">можно оставить пустым — согласовать позже</span>
						</p>
					</td>
				</tr>
				<tr>
					<th><label for="gr_address">Адрес</label></th>
					<td><input type="text" id="gr_address" name="address" class="large-text"
						value="<?php echo esc_attr( $data['address'] ); ?>" placeholder="Нужен, если отмечена доставка или забор"></td>
				</tr>
			</table>

			<h2>Статус</h2>
			<table class="form-table">
				<tr>
					<th><label for="gr_status">Статус</label></th>
					<td><?php gr_select( 'status', gr_statuses(), $data['status'] ); ?></td>
				</tr>
				<tr>
					<th><label for="gr_source">Источник заявки</label></th>
					<td><?php gr_select( 'source', gr_sources(), $data['source'] ); ?></td>
				</tr>
				<tr>
					<th><label for="gr_note">Комментарий</label></th>
					<td><textarea id="gr_note" name="note" rows="4" class="large-text"
						placeholder="Например: 12.05 клиент написал в WhatsApp, добавил 2 синих длинных"><?php echo esc_textarea( $data['note'] ); ?></textarea></td>
				</tr>
			</table>

			<?php submit_button( $id ? 'Сохранить изменения' : 'Создать бронь' ); ?>
		</form>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Обработчики
 * ---------------------------------------------------------------------- */

/** Сохранение брони (admin-post.php). */
function gr_handle_save_booking() {
	gr_check_request( 'gr_save_booking' );

	$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	if ( $id && ! gr_get_booking( $id ) ) {
		wp_die( 'Бронь не найдена.' );
	}

	$result = gr_save_booking( $_POST, $id );

	if ( is_wp_error( $result ) ) {
		// Запоминаем ошибки и введённые данные на минуту и возвращаемся к форме.
		$data = $result->get_error_data();
		set_transient(
			'gr_form_' . get_current_user_id(),
			array(
				'errors' => $result->get_error_messages(),
				'data'   => is_array( $data ) ? $data : array(),
			),
			MINUTE_IN_SECONDS
		);
		$args = $id ? array( 'action' => 'edit', 'id' => $id ) : array( 'action' => 'new' );
		gr_redirect( 'gr-bookings', $args );
	}

	gr_redirect( 'gr-bookings', array( 'gr_msg' => 'saved' ) );
}
add_action( 'admin_post_gr_save_booking', 'gr_handle_save_booking' );

/** Удаление брони (admin-post.php). */
function gr_handle_delete_booking() {
	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	gr_check_request( 'gr_delete_booking_' . $id );

	gr_delete_booking( $id );
	gr_redirect( 'gr-bookings', array( 'gr_msg' => 'deleted' ) );
}
add_action( 'admin_post_gr_delete_booking', 'gr_handle_delete_booking' );

/** Быстрая смена статуса из списка (admin-ajax.php). */
function gr_ajax_set_status() {
	check_ajax_referer( 'gr_admin', 'nonce' );
	if ( ! current_user_can( gr_capability() ) ) {
		wp_send_json_error( array( 'message' => 'Недостаточно прав.' ), 403 );
	}

	$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	$status = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';
	$result = gr_set_booking_status( $id, $status );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}
	wp_send_json_success( array( 'status' => $status ) );
}
add_action( 'wp_ajax_gr_set_status', 'gr_ajax_set_status' );
