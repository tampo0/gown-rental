<?php
/**
 * Шапка сайта: своя вместо шапки темы Kadence.
 * Пункты берутся из меню WordPress «Внешний вид → Меню» (область primary), так что их можно менять в админке.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Убираем шапку Kadence (и десктопную, и мобильную) — выводим свою.
add_action(
	'wp',
	function () {
		if ( ! is_admin() && function_exists( 'Kadence\header_markup' ) ) {
			remove_action( 'kadence_header', 'Kadence\header_markup' );
			add_action( 'kadence_header', 'gr_header_markup' );
		}
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'gr-header-fonts', 'https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600&family=Unbounded:wght@500;700&display=swap', array(), null );
		wp_enqueue_style( 'gr-header', GR_URL . 'public/header.css', array( 'gr-header-fonts' ), GR_VERSION );
		wp_enqueue_script( 'gr-header', GR_URL . 'public/header.js', array(), GR_VERSION, true );
	}
);

/** Пункты меню: [ title, url, current ]. */
function gr_header_items() {
	$locations = get_nav_menu_locations();
	$items     = empty( $locations['primary'] ) ? array() : (array) wp_get_nav_menu_items( $locations['primary'] );
	$current   = get_queried_object_id();
	$out       = array();
	foreach ( $items as $item ) {
		if ( (int) $item->menu_item_parent ) {
			continue; // подменю не используем
		}
		$out[] = array( $item->title, $item->url, $current && (int) $item->object_id === $current );
	}
	return $out;
}

function gr_header_markup() {
	$items   = gr_header_items();
	$c       = gr_contacts();
	$tel     = 'tel:' . preg_replace( '/[^\d+]/', '', $c['phone'] );
	$catalog = gr_catalog_url();
	$arrow   = '<svg class="grh-arrow" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	?>
	<header id="masthead" class="grh" data-grh>
		<div class="grh-bar">
			<a class="grh-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Mantiya Shymkent Prokat — на главную">
				<span class="grh-brand-name">Mantiya</span>
				<span class="grh-brand-sub">Shymkent · Prokat</span>
			</a>

			<nav class="grh-nav" aria-label="Главное меню">
				<ul class="grh-list" data-grh-list>
					<?php foreach ( $items as $item ) : ?>
						<li><a class="grh-link" data-grh-mag href="<?php echo esc_url( $item[1] ); ?>"<?php echo $item[2] ? ' aria-current="page"' : ''; ?>><span><?php echo esc_html( $item[0] ); ?></span></a></li>
					<?php endforeach; ?>
				</ul>
				<!-- «Рамка автофокуса»: четыре уголка, переезжают к пункту под курсором -->
				<span class="grh-focus" data-grh-focus aria-hidden="true"><i></i><i></i><i></i><i></i></span>
			</nav>

			<div class="grh-actions">
				<a class="grh-btn grh-btn-line" data-grh-mag href="<?php echo esc_url( gr_whatsapp_url() ); ?>" target="_blank" rel="noopener">WhatsApp</a>
				<a class="grh-btn grh-btn-gold" data-grh-mag href="<?php echo esc_url( $catalog ); ?>">Забронировать <?php echo $arrow; // phpcs:ignore -- статичный SVG ?></a>
			</div>

			<button class="grh-burger" type="button" aria-expanded="false" aria-controls="grh-sheet" data-grh-toggle>
				<span class="grh-burger-lines" aria-hidden="true"><i></i><i></i></span>
				<span class="screen-reader-text">Меню</span>
			</button>
		</div>

		<div class="grh-sheet" id="grh-sheet" data-grh-sheet hidden>
			<nav aria-label="Меню">
				<ul class="grh-sheet-list">
					<?php foreach ( $items as $item ) : ?>
						<li><a href="<?php echo esc_url( $item[1] ); ?>"<?php echo $item[2] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $item[0] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>
			<div class="grh-sheet-foot">
				<a class="grh-btn grh-btn-gold" href="<?php echo esc_url( $catalog ); ?>">Забронировать <?php echo $arrow; // phpcs:ignore ?></a>
				<a class="grh-btn grh-btn-line" href="<?php echo esc_url( gr_whatsapp_url() ); ?>" target="_blank" rel="noopener">Написать в WhatsApp</a>
				<p class="grh-sheet-meta"><a href="<?php echo esc_url( $tel ); ?>"><?php echo esc_html( $c['phone'] ); ?></a><br>Каждый день <?php echo esc_html( $c['hours']['Каждый день'] ); ?></p>
			</div>
		</div>
	</header>
	<?php
}
