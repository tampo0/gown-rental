<?php
/**
 * Меню «Прокат мантий» в админке, общие функции, настройки.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Право, нужное для работы с прокатом (администратор). */
function gr_capability() {
	return 'manage_options';
}

function gr_admin_menu() {
	// Счётчик новых броней рядом с пунктом меню.
	global $wpdb;
	$new_count = (int) $wpdb->get_var(
		$wpdb->prepare( 'SELECT COUNT(*) FROM ' . gr_bookings_table() . ' WHERE status = %s', 'new' )
	);
	$badge = $new_count ? ' <span class="awaiting-mod">' . $new_count . '</span>' : '';

	add_menu_page(
		'Прокат мантий',
		'Прокат мантий' . $badge,
		gr_capability(),
		'gr-bookings',
		'gr_bookings_page',
		'dashicons-welcome-learn-more', // иконка «шапка выпускника»
		26
	);
	add_submenu_page( 'gr-bookings', 'Брони', 'Брони' . $badge, gr_capability(), 'gr-bookings', 'gr_bookings_page' );
	add_submenu_page( 'gr-bookings', 'Склад', 'Склад', gr_capability(), 'gr-inventory', 'gr_inventory_page' );
	add_submenu_page( 'gr-bookings', 'Настройки проката', 'Настройки', gr_capability(), 'gr-settings', 'gr_settings_page' );
}
add_action( 'admin_menu', 'gr_admin_menu' );

/** JS и CSS только на страницах плагина. */
function gr_admin_assets( $hook ) {
	$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
	if ( 0 !== strpos( $page, 'gr-' ) ) {
		return;
	}
	if ( 'gr-settings' === $page ) {
		wp_enqueue_media(); // окно выбора фото из медиатеки
	}
	wp_enqueue_style( 'gr-admin', GR_URL . 'admin/admin.css', array(), GR_VERSION );
	wp_enqueue_script( 'gr-admin', GR_URL . 'admin/admin.js', array(), GR_VERSION, true );
	wp_localize_script(
		'gr-admin',
		'grAdmin',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'gr_admin' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'gr_admin_assets' );

/**
 * Сообщение после действия. Код передаётся в адресе: &gr_msg=saved
 */
function gr_admin_notice() {
	if ( empty( $_GET['gr_msg'] ) ) {
		return;
	}
	$messages = array(
		'saved'     => 'Бронь сохранена.',
		'deleted'   => 'Бронь удалена.',
		'inventory' => 'Склад обновлён.',
	);
	$code = sanitize_key( $_GET['gr_msg'] );
	if ( isset( $messages[ $code ] ) ) {
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $messages[ $code ] ) );
	}
}

/** Перенаправление на страницу плагина с сообщением. */
function gr_redirect( $page, $args = array() ) {
	wp_safe_redirect( add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) ) );
	exit;
}

/** Проверка прав и защитного токена для всех наших обработчиков. */
function gr_check_request( $nonce_action ) {
	if ( ! current_user_can( gr_capability() ) ) {
		wp_die( 'Недостаточно прав.' );
	}
	check_admin_referer( $nonce_action );
}

/* -------------------------------------------------------------------------
 * Настройки: цена, доставка, фото мантий.
 * Используем Settings API WordPress — он сам сохраняет значения в wp_options.
 * ---------------------------------------------------------------------- */

function gr_register_settings() {
	$int = array( 'type' => 'integer', 'sanitize_callback' => 'absint' );
	register_setting( 'gr_settings', 'gr_price_per_day', $int );
	register_setting( 'gr_settings', 'gr_delivery_fee', $int );
	register_setting( 'gr_settings', 'gr_free_delivery_from', $int );
	$text = array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' );
	register_setting( 'gr_settings', 'gr_pickup_address', $text );
	register_setting( 'gr_settings', 'gr_pickup_hours', $text );
	register_setting( 'gr_settings', 'gr_2gis_url', array( 'type' => 'string', 'sanitize_callback' => 'esc_url_raw' ) );
	foreach ( array_keys( gr_colors() ) as $color ) {
		register_setting( 'gr_settings', 'gr_photo_' . $color, $int ); // ID картинки в медиатеке
	}
}
add_action( 'admin_init', 'gr_register_settings' );

function gr_settings_page() {
	$hex = gr_color_hex();
	?>
	<div class="wrap gr-admin">
		<h1>Настройки проката</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'gr_settings' ); ?>

			<h2>Цены</h2>
			<table class="form-table">
				<tr>
					<th><label for="gr_price_per_day">Аренда одной мантии за сутки (₸)</label></th>
					<td><input type="number" min="0" step="100" id="gr_price_per_day" name="gr_price_per_day"
						value="<?php echo esc_attr( gr_price_per_day() ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="gr_delivery_fee">Доставка туда и обратно (₸)</label></th>
					<td>
						<input type="number" min="0" step="100" id="gr_delivery_fee" name="gr_delivery_fee"
							value="<?php echo esc_attr( gr_delivery_fee() ); ?>" class="regular-text">
						<p class="description">Одна сумма: привезти мантии и забрать их обратно.</p>
					</td>
				</tr>
				<tr>
					<th><label for="gr_free_delivery_from">Бесплатная доставка от</label></th>
					<td><input type="number" min="1" id="gr_free_delivery_from" name="gr_free_delivery_from"
						value="<?php echo esc_attr( gr_free_delivery_from() ); ?>" class="small-text"> мантий (включительно)</td>
				</tr>
			</table>

			<h2>Самовывоз</h2>
			<p class="description">Показывается в каталоге, когда клиент выбирает самовывоз.</p>
			<table class="form-table">
				<tr>
					<th><label for="gr_pickup_address">Адрес</label></th>
					<td><input type="text" id="gr_pickup_address" name="gr_pickup_address" value="<?php echo esc_attr( gr_pickup_address() ); ?>" class="large-text"></td>
				</tr>
				<tr>
					<th><label for="gr_pickup_hours">Часы работы</label></th>
					<td><input type="text" id="gr_pickup_hours" name="gr_pickup_hours" value="<?php echo esc_attr( gr_pickup_hours() ); ?>" class="large-text"></td>
				</tr>
				<tr>
					<th><label for="gr_2gis_url">Ссылка на 2ГИС</label></th>
					<td><input type="url" id="gr_2gis_url" name="gr_2gis_url" value="<?php echo esc_attr( gr_2gis_url() ); ?>" class="large-text"></td>
				</tr>
			</table>

			<h2>Фото мантий в каталоге</h2>
			<p class="description">Если фото не выбрано, в каталоге показывается рисунок мантии нужного цвета.</p>
			<div class="gr-photos">
				<?php
				foreach ( gr_colors() as $color => $label ) :
					$photo_id = (int) get_option( 'gr_photo_' . $color, 0 );
					$url      = gr_photo_url( $color, 'medium' );
					?>
					<div class="gr-photo-field">
						<strong><span class="gr-dot" style="background:<?php echo esc_attr( $hex[ $color ] ); ?>"></span><?php echo esc_html( $label ); ?></strong>
						<div class="gr-photo-preview">
							<?php if ( $url ) : ?>
								<img src="<?php echo esc_url( $url ); ?>" alt="">
							<?php else : ?>
								<span>нет фото</span>
							<?php endif; ?>
						</div>
						<input type="hidden" name="gr_photo_<?php echo esc_attr( $color ); ?>" value="<?php echo (int) $photo_id; ?>">
						<button type="button" class="button gr-pick-photo">Выбрать фото</button>
						<button type="button" class="button-link gr-clear-photo" <?php echo $photo_id ? '' : 'hidden'; ?>>Убрать</button>
					</div>
				<?php endforeach; ?>
			</div>

			<?php submit_button( 'Сохранить' ); ?>
		</form>
	</div>
	<?php
}
