<?php
/**
 * Разметка страницы каталога (шорткод [gown_catalog]).
 * Логику выбора и расчёта делает public/booking.js — классы gr-* не переименовывать.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Фото цвета для каталога: загруженное в «Настройках», иначе фото из плагина. */
function gr_catalog_photo( $color ) {
	$uploaded = gr_photo_url( $color );
	if ( $uploaded ) {
		return $uploaded;
	}
	$file = 'public/landing/color-' . $color . '.webp';
	return file_exists( GR_PATH . $file ) ? GR_URL . $file : '';
}

/** Все фото цвета для листания в каталоге: public/catalog/red-01.webp, red-02.webp … */
function gr_catalog_photos( $color ) {
	$files = glob( GR_PATH . 'public/catalog/' . $color . '-*.webp' ) ?: array();
	sort( $files );
	$urls = array_map( function ( $f ) { return GR_URL . 'public/catalog/' . basename( $f ) . '?v=' . filemtime( $f ); }, $files );
	if ( ! $urls && gr_catalog_photo( $color ) ) {
		$urls[] = gr_catalog_photo( $color );
	}
	return $urls;
}

/** Лесенка цен: «1–2 шт. — 5 000 ₸ …», от 20 — одна бесплатно. Используется в каталоге, на главной и в условиях. */
function gr_price_ladder_html() {
	$tiers = gr_price_tiers();
	$froms = array_keys( $tiers );
	ob_start();
	?>
	<div class="gr-ladder">
		<p class="gr-ladder-title">Чем больше мантий — тем дешевле каждая</p>
		<ol class="gr-ladder-steps">
			<?php foreach ( $froms as $i => $from ) : ?>
				<?php $to = isset( $froms[ $i + 1 ] ) ? $froms[ $i + 1 ] - 1 : 0; ?>
				<li data-from="<?php echo (int) $from; ?>" data-to="<?php echo (int) $to; ?>">
					<span class="gr-ladder-qty"><?php echo esc_html( $to ? ( $from === $to ? $from : $from . '–' . $to ) . ' шт.' : 'от ' . $from . ' шт.' ); ?></span>
					<span class="gr-ladder-price"><?php echo esc_html( gr_money( $tiers[ $from ] ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ol>
		<p class="gr-ladder-gift">Детская мантия — <?php echo esc_html( gr_money( gr_kids_price() ) ); ?>.<br>От <?php echo (int) gr_free_gown_from(); ?> мантий — одна для организатора бесплатно</p>
	</div>
	<?php
	return ob_get_clean();
}

/** Иконка-галочка (SVG вместо символа). */
function gr_check_icon() {
	return '<svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16"><path d="M5 12.5l4.5 4.5L19 7.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

function gr_catalog_shortcode() {
	wp_enqueue_style( 'gr-catalog' );
	wp_enqueue_script( 'gr-catalog' );

	$hex   = gr_color_hex();
	$today = current_time( 'Y-m-d' );
	$from  = gr_money( min( gr_price_tiers() ) );
	$free  = (int) gr_free_delivery_from();

	ob_start();
	?>
	<div class="gr-catalog grc">
		<noscript><p class="gr-noscript">Для бронирования включите JavaScript в браузере.</p></noscript>

		<header class="grc-head">
			<h1 class="grc-title">Каталог мантий</h1>
		</header>

		<form class="gr-form grc-layout" novalidate>
			<div class="grc-main">

				<!-- 1. Даты -->
				<section class="grc-step" aria-labelledby="grc-step-dates">
					<h2 id="grc-step-dates" class="grc-h2"><span class="grc-num">1</span>Дата и время</h2>
					<div class="grc-slate">
						<div class="grc-slate-fields">
							<label class="grc-field">
								<span>Дата</span>
								<input type="date" name="date" min="<?php echo esc_attr( $today ); ?>" required>
							</label>
							<label class="grc-field">
								<span>Начало аренды</span>
								<select name="time" required>
									<option value="">Время</option>
									<?php foreach ( gr_start_times() as $t ) : ?>
										<option value="<?php echo esc_attr( $t ); ?>"><?php echo esc_html( $t . ' – ' . gr_end_time( $t ) ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						</div>
						<p class="grc-note">Аренда в Шымкенте — <?php echo (int) gr_rental_hours(); ?> часа.<br>Другой город? <a href="<?php echo esc_url( gr_whatsapp_url() ); ?>" target="_blank" rel="noopener">Напишите в WhatsApp</a></p>
					</div>
				</section>

				<!-- 2. Мантии -->
				<section class="grc-step" aria-labelledby="grc-step-gowns">
					<h2 id="grc-step-gowns" class="grc-h2"><span class="grc-num">2</span>Цвета и размеры</h2>
					<p class="grc-note">Листайте фото, нажмите «Выбрать» под нужным цветом, затем размер и количество. Можно выбрать несколько цветов и размеров.</p>

					<div class="gr-cards grc-frames">
						<?php
						$n = 2;
						foreach ( gr_colors() as $color => $color_label ) :
							$photos  = gr_catalog_photos( $color );
							$offered = gr_color_sizes( $color );
							$missing = array_diff_key( gr_sizes(), $offered );
							?>
							<div class="gr-card grc-frame" data-color="<?php echo esc_attr( $color ); ?>" style="--swatch: <?php echo esc_attr( $hex[ $color ] ); ?>">
								<div class="grc-photo">
									<div class="grc-gallery" tabindex="0" aria-label="<?php echo esc_attr( 'Фото: ' . $color_label ); ?>">
										<?php if ( $photos ) : ?>
											<?php foreach ( $photos as $i => $url ) : ?>
												<img src="<?php echo esc_url( $url ); ?>" alt="<?php echo esc_attr( 'Мантия, цвет: ' . mb_strtolower( $color_label ) . ', фото ' . ( $i + 1 ) ); ?>" loading="lazy" width="900" height="1200">
											<?php endforeach; ?>
										<?php else : ?>
											<?php echo gr_gown_placeholder_svg( $hex[ $color ] ); // phpcs:ignore -- безопасный SVG из нашего кода ?>
										<?php endif; ?>
									</div>
									<?php if ( count( $photos ) > 1 ) : ?>
										<button type="button" class="grc-nav grc-nav-prev" aria-label="Предыдущее фото"><svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18"><path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
										<button type="button" class="grc-nav grc-nav-next" aria-label="Следующее фото"><svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18"><path d="M9 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
										<span class="grc-count" aria-hidden="true">1 / <?php echo count( $photos ); ?></span>
									<?php endif; ?>
									<span class="grc-tag">от <?php echo esc_html( $from ); ?></span>
									<span class="grc-corner grc-corner-tl" aria-hidden="true"></span>
									<span class="grc-corner grc-corner-tr" aria-hidden="true"></span>
									<span class="grc-corner grc-corner-bl" aria-hidden="true"></span>
									<span class="grc-corner grc-corner-br" aria-hidden="true"></span>
								</div>
								<button type="button" class="gr-color-btn grc-frame-btn" aria-pressed="false">
									<span class="grc-edge">
										<span class="grc-edge-num"><?php echo esc_html( sprintf( '%02dA', $n ) ); ?></span>
										<span class="grc-edge-name"><?php echo esc_html( $color_label ); ?></span>
										<span class="grc-edge-pick">
											<span class="grc-pick-off">Выбрать</span>
											<span class="grc-pick-on"><?php echo gr_check_icon(); // phpcs:ignore ?> Выбран</span>
										</span>
									</span>
								</button>

								<div class="gr-sizes grc-sizes" hidden>
									<?php foreach ( $offered as $size => $size_label ) : ?>
										<div class="gr-size grc-size" data-size="<?php echo esc_attr( $size ); ?>">
											<button type="button" class="gr-size-btn grc-size-btn" aria-pressed="false">
												<span class="grc-size-box" aria-hidden="true"><?php echo gr_check_icon(); // phpcs:ignore ?></span>
												<span class="grc-size-name"><?php echo esc_html( $size_label ); ?></span>
											</button>
											<span class="gr-free grc-free"></span>
											<div class="gr-qty grc-qty" hidden>
												<button type="button" class="gr-qty-minus" aria-label="Меньше"><svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16"><path d="M6 12h12" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg></button>
												<input type="number" class="gr-qty-input" value="1" min="1" max="<?php echo (int) gr_max_item_quantity(); ?>"
													aria-label="<?php echo esc_attr( 'Количество: ' . $color_label . ', ' . $size_label ); ?>">
												<button type="button" class="gr-qty-plus" aria-label="Больше"><svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16"><path d="M6 12h12M12 6v12" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg></button>
											</div>
										</div>
									<?php endforeach; ?>
									<?php if ( $missing ) : ?>
										<p class="grc-missing"><?php echo esc_html( implode( ', ', $missing ) . ' размер — нет в этом цвете' ); ?></p>
									<?php endif; ?>
								</div>
							</div>
							<?php
							$n++;
						endforeach;
						?>
					</div>
				</section>

				<!-- 3. Получение: самовывоз или доставка -->
				<section class="grc-step" aria-labelledby="grc-step-delivery">
					<h2 id="grc-step-delivery" class="grc-h2"><span class="grc-num">3</span>Получение</h2>

					<div class="grc-options" role="radiogroup" aria-labelledby="grc-step-delivery">
						<div class="grc-option">
							<label class="grc-radio">
								<input type="radio" name="delivery" value="0" checked>
								<span class="grc-radio-dot" aria-hidden="true"></span>
								<span class="grc-radio-title">Самовывоз</span>
								<span class="grc-radio-price">Шымкент</span>
							</label>
							<div class="grc-option-fields" data-show="self">
								<p class="grc-place">
									<svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="9.5" r="2.5" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
									<span>
										<strong><?php echo esc_html( gr_pickup_address() ); ?></strong>
										<span class="grc-place-hours"><?php echo esc_html( gr_pickup_hours() ); ?></span>
									</span>
								</p>
								<a class="grc-map-link" href="<?php echo esc_url( gr_2gis_url() ); ?>" target="_blank" rel="noopener">
									Открыть в 2ГИС
									<svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16"><path d="M7 17L17 7M9 7h8v8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
								</a>
								<p class="grc-note"><strong>Залог:</strong> оригинал удостоверения личности. Если его нет — фото удостоверения и 10&nbsp;000&nbsp;₸, вернём при возврате мантий.</p>
							</div>
						</div>

						<div class="grc-option">
							<label class="grc-radio">
								<input type="radio" name="delivery" value="1" disabled>
								<span class="grc-radio-dot" aria-hidden="true"></span>
								<span class="grc-radio-title">Доставка</span>
								<span class="grc-radio-price">от <?php echo (int) $free; ?> мантий, бесплатно</span>
							</label>
							<div class="grc-option-fields" data-show="delivery" hidden>
								<p class="grc-note">Привезём к началу аренды и сами заберём после.</p>
								<label class="grc-field gr-address">
									<span>Адрес *</span>
									<input type="text" name="address" autocomplete="street-address" placeholder="Город, улица, дом, квартира или учебное заведение">
								</label>
							</div>
						</div>
					</div>
				</section>

				<!-- 4. Контакты -->
				<section class="grc-step" aria-labelledby="grc-step-contacts">
					<h2 id="grc-step-contacts" class="grc-h2"><span class="grc-num">4</span>Ваши данные</h2>
					<div class="grc-row">
						<label class="grc-field">
							<span>Имя *</span>
							<input type="text" name="customer_name" autocomplete="name" required>
						</label>
						<label class="grc-field">
							<span>Телефон с WhatsApp *</span>
							<input type="tel" name="phone" autocomplete="tel" placeholder="+7 700 123 45 67" required>
						</label>
					</div>
					<label class="grc-field">
						<span>Email *</span>
						<input type="email" name="email" autocomplete="email" required>
					</label>
					<label class="grc-field">
						<span>Комментарий</span>
						<textarea name="note" rows="3"></textarea>
					</label>

					<?php // Ловушка для спам-ботов: люди это поле не видят и не заполняют. ?>
					<div class="gr-hp" aria-hidden="true">
						<label>Сайт <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
					</div>
				</section>
			</div>

			<!-- Квитанция заказа (на компьютере прилипает справа) -->
			<aside class="grc-aside">
				<?php echo gr_price_ladder_html(); // phpcs:ignore -- разметка из нашей функции ?>
				<div class="gr-summary grc-receipt" aria-live="polite">
					<h2 class="grc-receipt-title">Ваш заказ</h2>
					<div class="gr-summary-body"><p class="gr-muted">Выберите дату, время и мантии.</p></div>
				</div>
				<div class="gr-errors" role="alert"></div>
				<label class="grc-consent">
					<input type="checkbox" name="consent" value="1" required>
					<span>Согласен на обработку персональных данных по <a href="<?php echo esc_url( get_privacy_policy_url() ); ?>" target="_blank" rel="noopener">политике конфиденциальности</a></span>
				</label>
				<button type="submit" class="gr-submit grc-submit" disabled>Забронировать</button>
				<p class="gr-hint">Бронь подтверждается после оплаты в WhatsApp — до этого мантии за вами не закреплены. Если на номере нет WhatsApp, заказ не принимается.</p>
			</aside>
		</form>

		<div class="gr-success grc-success" hidden>
			<h2>Бронь принята</h2>
			<div class="gr-success-text"></div>
			<button type="button" class="gr-again">Оформить ещё одну</button>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
