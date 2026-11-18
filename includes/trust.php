<?php
/**
 * Страница «Нам доверяют» [gown_trust] и полоса с логотипами для главной.
 * Список вузов и количество мантий — в gr_trusted_unis().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Вузы, которым поставляли мантии. featured — крупно в первом ряду. */
function gr_trusted_unis() {
	return array(
		array(
			'key'      => 'enu',
			'short'    => 'ЕНУ',
			'name'     => 'Евразийский национальный университет им. Л. Н. Гумилёва',
			'city'     => 'Астана',
			'count'    => 500,
			'featured' => true,
		),
		array(
			'key'      => 'kaznu',
			'short'    => 'КазНУ',
			'name'     => 'Казахский национальный университет им. аль‑Фараби',
			'city'     => 'Алматы',
			'count'    => 350,
			'featured' => true,
		),
		array(
			'key'      => 'apec',
			'short'    => 'APEC PetroTechnic',
			'name'     => 'APEC PetroTechnic Higher College',
			'city'     => 'Атырау',
			'count'    => 500,
			'featured' => false,
		),
		array(
			'key'      => 'narxoz',
			'short'    => 'Нархоз',
			'name'     => 'Университет Нархоз',
			'city'     => 'Алматы',
			'count'    => 100,
			'featured' => false,
		),
	);
}

function gr_trusted_total() {
	return array_sum( wp_list_pluck( gr_trusted_unis(), 'count' ) );
}

function gr_logo_url( $key ) {
	return GR_URL . 'public/logos/' . $key . '.webp';
}

/** Адрес страницы «Нам доверяют» (страница со шорткодом [gown_trust]). */
function gr_trust_url() {
	$page = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 's' => '[gown_trust]', 'numberposts' => 1, 'fields' => 'ids' ) );
	return $page ? get_permalink( $page[0] ) : '';
}

function gr_num( $n ) {
	return number_format( (int) $n, 0, '', ' ' );
}

/* -------------------------------------------------------------------------
 * Стили — те же, что у «Условий» и «Контактов» (public/pages.css)
 * ---------------------------------------------------------------------- */

function gr_trust_assets() {
	if ( ! gr_page_has( 'gown_trust' ) ) {
		return;
	}
	wp_enqueue_style( 'gr-pages-fonts', 'https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600&family=Unbounded:wght@500;700&display=swap', array(), null );
	wp_enqueue_style( 'gr-pages', GR_URL . 'public/pages.css', array( 'gr-pages-fonts' ), GR_VERSION );
}
add_action( 'wp_enqueue_scripts', 'gr_trust_assets' );

function gr_trust_body_class( $classes ) {
	if ( gr_page_has( 'gown_trust' ) ) {
		$classes[] = 'gr-landing-page';
		$classes[] = 'gr-studio-page';
	}
	return $classes;
}
add_filter( 'body_class', 'gr_trust_body_class' );

/* -------------------------------------------------------------------------
 * [gown_trust]
 * ---------------------------------------------------------------------- */

/** Цвета долей в полосе «1 450» — по порядку вузов. */
function gr_trust_shades() {
	return array( '#121315', '#8f6d22', '#d6b25e', '#9a9da3' );
}

function gr_trust_shortcode() {
	$unis   = gr_trusted_unis();
	$total  = gr_trusted_total();
	$shades = gr_trust_shades();
	$c      = gr_contacts();

	ob_start();
	?>
	<div class="grp grt">

		<!-- Итог: сколько мантий и как они распределились по вузам -->
		<header class="grt-hero">
			<h1 class="grp-title">Нам доверяют</h1>
			<div class="grt-total">
				<p class="grt-total-num"><?php echo esc_html( gr_num( $total ) ); ?><span>мантий для выпускников университетов и колледжей Казахстана</span></p>
				<div class="grt-bar" role="img" aria-label="<?php echo esc_attr( implode( ', ', array_map( function ( $u ) { return $u['short'] . ' — ' . $u['count']; }, $unis ) ) ); ?>">
					<?php foreach ( $unis as $i => $u ) : ?>
						<span style="flex-grow: <?php echo (int) $u['count']; ?>; background: <?php echo esc_attr( $shades[ $i % count( $shades ) ] ); ?>"></span>
					<?php endforeach; ?>
				</div>
				<ul class="grt-legend">
					<?php foreach ( $unis as $i => $u ) : ?>
						<li><i style="background: <?php echo esc_attr( $shades[ $i % count( $shades ) ] ); ?>"></i><?php echo esc_html( $u['short'] ); ?> <b><?php echo esc_html( gr_num( $u['count'] ) ); ?></b></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</header>

		<!-- Доска почёта: по строке на вуз -->
		<ol class="grt-roll" aria-label="Вузы, которым мы поставляли мантии">
			<?php foreach ( $unis as $u ) : ?>
				<li class="grt-row<?php echo $u['featured'] ? ' is-featured' : ''; ?>">
					<div class="grt-row-logo">
						<img src="<?php echo esc_url( gr_logo_url( $u['key'] ) ); ?>" alt="<?php echo esc_attr( 'Логотип: ' . $u['name'] ); ?>" loading="lazy">
					</div>
					<div class="grt-row-name">
						<h2><?php echo esc_html( $u['name'] ); ?></h2>
						<p><?php echo esc_html( $u['city'] ); ?></p>
					</div>
					<p class="grt-row-count"><span><?php echo esc_html( gr_num( $u['count'] ) ); ?></span>мантий</p>
				</li>
			<?php endforeach; ?>
		</ol>

		<!-- Живое подтверждение: выпускницы в наших мантиях -->
		<figure class="grt-photo">
			<img src="<?php echo esc_url( gr_landing_img( 'group-diplomas' ) ); ?>"
				srcset="<?php echo esc_url( gr_landing_img( 'group-diplomas' ) ); ?> 1200w, <?php echo esc_url( gr_landing_img( 'group-diplomas-2400' ) ); ?> 2400w"
				sizes="(max-width: 1520px) 100vw, 1520px"
				alt="Выпускницы в синих мантиях поднимают дипломы" width="2400" height="1247" loading="lazy" decoding="async">
		</figure>

		<section class="grt-b2b" aria-labelledby="grt-b2b-title">
			<div>
				<h2 id="grt-b2b-title" class="grp-h2">Нужны мантии для выпуска вашего вуза?</h2>
				<p class="grp-text">Поставляем партии мантий для выпускных — по прямому договору или через госзакупки. Поможем подобрать цвета и размеры под ваш выпуск и привезём к сроку.</p>
			</div>
			<div class="grp-cta-actions">
				<a class="grp-btn grp-btn-gold" href="<?php echo esc_url( gr_whatsapp_url() ); ?>" target="_blank" rel="noopener">Обсудить в WhatsApp</a>
				<a class="grp-btn grp-btn-line" href="<?php echo esc_attr( 'mailto:' . $c['email'] ); ?>">Написать на почту</a>
			</div>
		</section>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'gown_trust', 'gr_trust_shortcode' );

/* -------------------------------------------------------------------------
 * Полоса с логотипами для главной
 * ---------------------------------------------------------------------- */

function gr_trust_strip() {
	$url = gr_trust_url();
	ob_start();
	?>
	<section class="grl-trust" aria-labelledby="grl-trust-title">
		<div class="grl-trust-text">
			<h2 id="grl-trust-title" class="grl-trust-title">Нам доверяют университеты</h2>
			<p><?php echo esc_html( gr_num( gr_trusted_total() ) ); ?> мантий для выпускников ЕНУ, КазНУ, Нархоза и APEC PetroTechnic.</p>
			<?php if ( $url ) : ?>
				<a class="grl-trust-link" href="<?php echo esc_url( $url ); ?>">Подробнее <svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18"><path d="M5 12h14M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
			<?php endif; ?>
		</div>
		<ul class="grl-trust-logos">
			<?php foreach ( gr_trusted_unis() as $u ) : ?>
				<li><img src="<?php echo esc_url( gr_logo_url( $u['key'] ) ); ?>" alt="<?php echo esc_attr( $u['name'] ); ?>" loading="lazy"></li>
			<?php endforeach; ?>
		</ul>
	</section>
	<?php
	return ob_get_clean();
}
