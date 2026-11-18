<?php
/**
 * Главная страница: шорткод [gown_landing].
 *
 * Все тексты главной — в функции gr_landing_shortcode() ниже: их можно менять прямо здесь.
 * Фото лежат в public/landing/.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Ссылка на WhatsApp (тот же номер, что телефон на странице «Контакты»). */
function gr_whatsapp_url() {
	return 'https://wa.me/77055742626?text=' . rawurlencode( 'Здравствуйте! Хочу арендовать мантию' );
}

/** Адрес страницы каталога (страница, где стоит [gown_catalog]). */
function gr_catalog_url() {
	$page = get_posts(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			's'           => '[gown_catalog]',
			'numberposts' => 1,
			'fields'      => 'ids',
		)
	);
	return $page ? get_permalink( $page[0] ) : home_url( '/' );
}

function gr_landing_img( $name ) {
	return GR_URL . 'public/landing/' . $name . '.webp';
}

/* -------------------------------------------------------------------------
 * Стили и скрипт — только на странице с шорткодом
 * ---------------------------------------------------------------------- */

function gr_landing_assets() {
	if ( ! is_singular() || ! has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), 'gown_landing' ) ) {
		return;
	}
	wp_enqueue_style(
		'gr-landing-fonts',
		'https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600&family=Unbounded:wght@500;700&display=swap',
		array(),
		null
	);
	wp_enqueue_style( 'gr-landing', GR_URL . 'public/landing.css', array( 'gr-landing-fonts' ), GR_VERSION );
	wp_enqueue_script( 'gr-landing', GR_URL . 'public/landing.js', array(), GR_VERSION, true );
	wp_localize_script(
		'gr-landing',
		'grLanding',
		array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'gr_public' ),
			'today'      => current_time( 'Y-m-d' ),
			'catalogUrl' => gr_catalog_url(),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'gr_landing_assets' );

/** Класс для <body>, чтобы подстроить шапку темы под главную. */
function gr_landing_body_class( $classes ) {
	if ( is_singular() && has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), 'gown_landing' ) ) {
		$classes[] = 'gr-landing-page';
	}
	if ( is_singular() && has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), 'gown_catalog' ) ) {
		$classes[] = 'gr-landing-page';
		$classes[] = 'gr-catalog-page';
	}
	return $classes;
}
add_filter( 'body_class', 'gr_landing_body_class' );

/* -------------------------------------------------------------------------
 * Разметка
 * ---------------------------------------------------------------------- */

function gr_landing_shortcode() {
	$price    = gr_money( gr_price_per_day() );
	$delivery = gr_money( gr_delivery_fee() );
	$free     = (int) gr_free_delivery_from();
	$catalog  = gr_catalog_url();
	$colors   = gr_colors();
	$hex      = gr_color_hex();

	// Кадры контактного листа: цвет → фото.
	$frames = array(
		'red'    => 'color-red',
		'blue'   => 'color-blue',
		'green'  => 'color-green',
		'purple' => 'color-purple',
		'black'  => 'color-black',
	);

	ob_start();
	?>
	<div class="grl" id="grl">

		<!-- 1. Первый экран: белый фон студии, даты и выпускница в кадре -->
		<section class="grl-hero" aria-labelledby="grl-title">
			<div class="grl-hero-copy">
				<h1 id="grl-title" class="grl-display">
					Мантии на&nbsp;выпускной <span class="grl-display-sub">на&nbsp;ваши даты</span>
				</h1>

				<form class="grl-slate" action="<?php echo esc_url( $catalog ); ?>" method="get" novalidate>
					<p class="grl-dates-title">Когда нужны мантии?</p>
					<div class="grl-slate-fields">
						<label class="grl-field">
							<span>Получить</span>
							<input type="date" name="from" required>
						</label>
						<label class="grl-field">
							<span>Вернуть</span>
							<input type="date" name="to" required>
						</label>
					</div>

					<div class="grl-slate-result" aria-live="polite">
						<p class="grl-slate-hint" data-grl-hint>Выберите обе даты — сразу покажем, какие цвета свободны.</p>
						<ul class="grl-counts" data-grl-counts hidden>
							<?php foreach ( $colors as $key => $label ) : ?>
								<li style="--fabric: <?php echo esc_attr( $hex[ $key ] ); ?>">
									<span class="grl-chip" aria-hidden="true"></span>
									<span class="grl-count-label"><?php echo esc_html( $label ); ?></span>
									<span class="grl-sr" data-grl-count="<?php echo esc_attr( $key ); ?>"></span>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>

					<a class="grl-btn grl-btn-gold" data-grl-cta href="<?php echo esc_url( $catalog ); ?>">
						Выбрать мантии<span data-grl-cta-dates></span>
						<svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><path d="M5 12h14M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</a>
				</form>

				<p class="grl-terms">
					Доставка и вывоз — <?php echo esc_html( $delivery ); ?>
				</p>
			</div>

			<figure class="grl-hero-frame">
				<span class="grl-corner grl-corner-tl" aria-hidden="true"></span>
				<span class="grl-corner grl-corner-tr" aria-hidden="true"></span>
				<span class="grl-corner grl-corner-bl" aria-hidden="true"></span>
				<span class="grl-corner grl-corner-br" aria-hidden="true"></span>
				<img class="grl-hero-img" src="<?php echo esc_url( gr_landing_img( 'hero-green' ) ); ?>" alt="Выпускница в зелёной мантии и шапочке держит диплом" width="1353" height="1800" fetchpriority="high">

				<!-- Ценник-бирка на мантии -->
				<p class="grl-pricetag">
					<span class="grl-pricetag-hole" aria-hidden="true"></span>
					<span class="grl-pricetag-sum"><?php echo esc_html( number_format( gr_price_per_day(), 0, '', ' ' ) ); ?><small>₸</small></span>
					<span class="grl-pricetag-per">за мантию в сутки</span>
					<span class="grl-pricetag-free">Доставка 0 ₸ от <?php echo esc_html( $free ); ?> мантий</span>
				</p>

				<figcaption class="grl-caption">
					<span>Кадр 01</span>
					<span>Зелёная · Стандартный</span>
				</figcaption>
			</figure>
		</section>

		<!-- 2. Контактный лист: четыре цвета на сером фоне -->
		<section class="grl-sheet" aria-labelledby="grl-colors-title">
			<div class="grl-sheet-head">
				<h2 id="grl-colors-title" class="grl-h2">Пять цветов</h2>
				<p>Размеры: детский, стандартный и длинный. Шапочка с кисточкой и лента в комплекте.</p>
			</div>

			<ol class="grl-strip">
				<?php
				$n = 2;
				foreach ( $frames as $key => $img ) :
					?>
					<li class="grl-frame">
						<a href="<?php echo esc_url( $catalog ); ?>" data-grl-color-link="<?php echo esc_attr( $key ); ?>">
							<span class="grl-frame-img">
								<img src="<?php echo esc_url( gr_landing_img( $img ) ); ?>" alt="Мантия, цвет: <?php echo esc_attr( mb_strtolower( $colors[ $key ] ) ); ?>" width="825" height="1100" loading="lazy">
							</span>
							<span class="grl-frame-edge">
								<span><?php echo esc_html( sprintf( '%02dA', $n ) ); ?></span>
								<span class="grl-frame-name"><?php echo esc_html( $colors[ $key ] ); ?></span>
								<span class="grl-frame-free" data-grl-frame-free="<?php echo esc_attr( $key ); ?>"></span>
							</span>
						</a>
					</li>
					<?php
					$n++;
				endforeach;
				?>
			</ol>
		</section>

		<!-- 5. Семья и детский размер -->
		<section class="grl-family" aria-labelledby="grl-family-title">
			<figure class="grl-family-photo grl-family-photo-a">
				<span class="grl-corner grl-corner-tl" aria-hidden="true"></span>
				<span class="grl-corner grl-corner-tr" aria-hidden="true"></span>
				<span class="grl-corner grl-corner-bl" aria-hidden="true"></span>
				<span class="grl-corner grl-corner-br" aria-hidden="true"></span>
				<figcaption class="grl-caption"><span>Кадр 07</span><span>Детский размер</span></figcaption>
				<img src="<?php echo esc_url( gr_landing_img( 'mother-baby' ) ); ?>" alt="Мама в мантии поднимает малыша в маленькой мантии и шапочке" width="1050" height="1400" loading="lazy">
			</figure>
			<div class="grl-family-copy">
				<h2 id="grl-family-title" class="grl-h2">Размер есть для каждого в кадре</h2>
				<p>Детские мантии — чтобы ваши дети тоже были в кадре вместе с вами.</p>
			</div>
		</section>

		<!-- 6. Нам доверяют -->
		<?php echo gr_trust_strip(); // phpcs:ignore -- разметка из includes/trust.php ?>




		<!-- 4. Как забронировать -->
		<section class="grl-steps" aria-labelledby="grl-steps-title">
			<figure class="grl-steps-photo">
				<img src="<?php echo esc_url( gr_landing_img( 'seated-navy' ) ); ?>" alt="Выпускница в синей мантии сидит в кресле с красным дипломом" width="1050" height="1400" loading="lazy">
			</figure>
			<div class="grl-steps-copy">
				<h2 id="grl-steps-title" class="grl-h2">Как забронировать</h2>
				<ol class="grl-step-list">
					<li>
						<h3>Даты</h3>
						<p>День получения и день возврата. Сразу видно, есть ли мантии в наличии.</p>
					</li>
					<li>
						<h3>Цвета и размеры</h3>
						<p>Нажмите на цвет, затем на размер и укажите количество. Можно выбрать несколько.</p>
					</li>
					<li>
						<h3>Доставка или самовывоз</h3>
						<p>Самовывоз бесплатно или доставка — <?php echo esc_html( $delivery ); ?> к точному времени. После праздника заберём мантии сами.</p>
					</li>
				</ol>
				<div class="grl-steps-actions">
					<a class="grl-btn grl-btn-gold" href="<?php echo esc_url( $catalog ); ?>" data-grl-cta-plain>
						Перейти в каталог
						<svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><path d="M5 12h14M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</a>
					<a class="grl-btn grl-btn-line" href="<?php echo esc_url( gr_whatsapp_url() ); ?>" target="_blank" rel="noopener">Написать в WhatsApp</a>
				</div>
			</div>
		</section>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'gown_landing', 'gr_landing_shortcode' );
