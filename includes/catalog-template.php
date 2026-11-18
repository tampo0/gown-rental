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

/** Иконка-галочка (SVG вместо символа). */
function gr_check_icon() {
	return '<svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16"><path d="M5 12.5l4.5 4.5L19 7.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

function gr_catalog_shortcode() {
	wp_enqueue_style( 'gr-catalog' );
	wp_enqueue_script( 'gr-catalog' );

	$hex   = gr_color_hex();
	$today = current_time( 'Y-m-d' );
	$price = gr_money( gr_price_per_day() );
	$fee   = gr_money( gr_delivery_fee() );
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
					<h2 id="grc-step-dates" class="grc-h2"><span class="grc-num">1</span>Даты аренды</h2>
					<div class="grc-slate">
						<div class="grc-slate-fields">
							<label class="grc-field">
								<span>Получаю</span>
								<input type="date" name="date_from" min="<?php echo esc_attr( $today ); ?>" required>
							</label>
							<label class="grc-field">
								<span>Возвращаю</span>
								<input type="date" name="date_to" min="<?php echo esc_attr( $today ); ?>" required>
							</label>
						</div>
						<p class="grc-note">День возврата тоже считается днём аренды.</p>
					</div>
				</section>

				<!-- 2. Мантии -->
				<section class="grc-step" aria-labelledby="grc-step-gowns">
					<h2 id="grc-step-gowns" class="grc-h2"><span class="grc-num">2</span>Цвета и размеры</h2>
					<p class="grc-note">Нажмите на кадр, чтобы выбрать цвет, затем на размер и укажите количество. Можно выбрать несколько цветов и размеров.</p>

					<div class="gr-cards grc-frames">
						<?php
						$n = 2;
						foreach ( gr_colors() as $color => $color_label ) :
							$photo   = gr_catalog_photo( $color );
							$offered = gr_color_sizes( $color );
							$missing = array_diff_key( gr_sizes(), $offered );
							?>
							<div class="gr-card grc-frame" data-color="<?php echo esc_attr( $color ); ?>" style="--swatch: <?php echo esc_attr( $hex[ $color ] ); ?>">
								<button type="button" class="gr-color-btn grc-frame-btn" aria-pressed="false">
									<span class="grc-photo">
										<?php if ( $photo ) : ?>
											<img src="<?php echo esc_url( $photo ); ?>" alt="<?php echo esc_attr( 'Мантия бакалавра, цвет: ' . mb_strtolower( $color_label ) ); ?>" loading="lazy">
										<?php else : ?>
											<?php echo gr_gown_placeholder_svg( $hex[ $color ] ); // phpcs:ignore -- безопасный SVG из нашего кода ?>
										<?php endif; ?>
										<span class="grc-tag"><?php echo esc_html( $price ); ?><small>/ сутки</small></span>
										<span class="grc-corner grc-corner-tl" aria-hidden="true"></span>
										<span class="grc-corner grc-corner-tr" aria-hidden="true"></span>
										<span class="grc-corner grc-corner-bl" aria-hidden="true"></span>
										<span class="grc-corner grc-corner-br" aria-hidden="true"></span>
									</span>
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
								<span class="grc-radio-price">бесплатно</span>
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
								<p class="grc-note">Мантии вернуть в последний день брони по этому же адресу.</p>
							</div>
						</div>

						<div class="grc-option">
							<label class="grc-radio">
								<input type="radio" name="delivery" value="1">
								<span class="grc-radio-dot" aria-hidden="true"></span>
								<span class="grc-radio-title">Доставка</span>
								<span class="grc-radio-price gr-fee" data-fee="delivery"><?php echo esc_html( $fee ); ?></span>
							</label>
							<div class="grc-option-fields" data-show="delivery" hidden>
								<p class="grc-note">Привезём мантии и сами заберём их после праздника. Бесплатно от <?php echo esc_html( $free ); ?> мантий.</p>
								<label class="grc-field grc-field-small">
									<span>Время доставки * <span class="gr-date-hint" data-date="from"></span></span>
									<input type="time" name="delivery_time">
								</label>
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
							<span>Телефон (WhatsApp) *</span>
							<input type="tel" name="phone" autocomplete="tel" placeholder="+7 700 123 45 67" required>
						</label>
					</div>
					<label class="grc-field">
						<span>Email</span>
						<input type="email" name="email" autocomplete="email">
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
				<div class="gr-summary grc-receipt" aria-live="polite">
					<h2 class="grc-receipt-title">Ваш заказ</h2>
					<div class="gr-summary-body"><p class="gr-muted">Выберите даты и мантии.</p></div>
				</div>
				<div class="gr-errors" role="alert"></div>
				<button type="submit" class="gr-submit grc-submit" disabled>Забронировать</button>
				<p class="gr-hint">Оплата после брони, мы напишем вам в WhatsApp.</p>
			</aside>
		</form>

		<div class="gr-success grc-success" hidden>
			<h2>Заявка отправлена</h2>
			<p class="gr-success-text"></p>
			<button type="button" class="gr-again">Оформить ещё одну</button>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
