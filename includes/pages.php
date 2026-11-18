<?php
/**
 * Страницы «Условия аренды» [gown_terms] и «Контакты» [gown_contacts]
 * в том же стиле, что главная и каталог. Тексты — прямо здесь.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Штрафы за утерю (₸). Меняйте суммы здесь. */
function gr_loss_fees() {
	return array(
		'Мантия'                => 12000,
		'Шапочка с кисточкой'   => 6000,
		'Лента'                 => 3000,
	);
}

/** Контакты (со страницы «Контакты»). */
function gr_contacts() {
	return array(
		'phone'      => '+7 705 574 26 26',
		'email'      => gr_contact_email(),
		'hours'      => array(
			'Каждый день' => '9:30–18:00',
		),
		'hours_note' => '',
	);
}

/* -------------------------------------------------------------------------
 * Стили — только на этих страницах
 * ---------------------------------------------------------------------- */

function gr_page_has( $tag ) {
	return is_singular() && has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), $tag );
}

function gr_pages_assets() {
	if ( ! gr_page_has( 'gown_terms' ) && ! gr_page_has( 'gown_contacts' ) && ! gr_page_has( 'gown_privacy' ) ) {
		return;
	}
	wp_enqueue_style( 'gr-pages-fonts', 'https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600&family=Unbounded:wght@500;700&display=swap', array(), null );
	wp_enqueue_style( 'gr-pages', GR_URL . 'public/pages.css', array( 'gr-pages-fonts', 'gr-ladder' ), GR_VERSION );
}
add_action( 'wp_enqueue_scripts', 'gr_pages_assets' );

function gr_pages_body_class( $classes ) {
	if ( gr_page_has( 'gown_terms' ) || gr_page_has( 'gown_contacts' ) || gr_page_has( 'gown_privacy' ) ) {
		$classes[] = 'gr-landing-page';
		$classes[] = 'gr-studio-page';
	}
	return $classes;
}
add_filter( 'body_class', 'gr_pages_body_class' );

function gr_icon( $name ) {
	$paths = array(
		'phone' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
		'chat'  => '<path d="M4 20l1.4-4A8 8 0 1 1 8 18.6z"/><path d="M9 10h6M9 13.5h4"/>',
		'mail'  => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
		'pin'   => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
		'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'arrow' => '<path d="M7 17L17 7M9 7h8v8"/>',
	);
	return '<svg aria-hidden="true" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $paths[ $name ] . '</svg>';
}

/* -------------------------------------------------------------------------
 * [gown_terms] — Условия аренды
 * ---------------------------------------------------------------------- */

function gr_terms_shortcode() {
	$free = (int) gr_free_delivery_from();
	$wa   = gr_whatsapp_url();

	ob_start();
	?>
	<div class="grp">
		<header class="grp-head">
			<h1 class="grp-title">Условия аренды</h1>
		</header>

		<div class="grp-terms">
			<div class="grp-terms-main">

				<section class="grp-block" aria-labelledby="grp-how">
					<h2 id="grp-how" class="grp-h2">Как проходит аренда в Шымкенте</h2>
					<ol class="grp-steps">
						<li>
							<h3>Бронь</h3>
							<p>Выберите в <a href="<?php echo esc_url( gr_catalog_url() ); ?>">каталоге</a> дату, время начала, цвета и размеры. После брони отправьте нам заявку в WhatsApp — она откроется сама. Без WhatsApp заказ не принимается.</p>
						</li>
						<li>
							<h3>Оплата</h3>
							<p>В WhatsApp, после заявки. Бронь подтверждается только после оплаты — до этого мантии за вами не закреплены.</p>
						</li>
						<li>
							<h3>Получение</h3>
							<p>Самовывоз — <?php echo esc_html( gr_pickup_address() ); ?>. Доставка — бесплатно, от <?php echo (int) $free; ?> мантий.</p>
						</li>
						<li>
							<h3>Аренда — <?php echo (int) gr_rental_hours(); ?> часа</h3>
							<p>Начало — с <?php echo esc_html( gr_start_window() ); ?>, вернуть через <?php echo (int) gr_rental_hours(); ?> часа. Например, взяли в 11:30 — вернуть до 14:30. Последнее время начала — 15:00, вернуть до 18:00.</p>
						</li>
					</ol>
				</section>

				<section class="grp-block" aria-labelledby="grp-cities">
					<h2 id="grp-cities" class="grp-h2">Другие города</h2>
					<p class="grp-text">Аренда на сутки — с учётом времени на перевозку. Заказ — через <a href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener">WhatsApp</a>.</p>
				</section>

				<section class="grp-block" aria-labelledby="grp-prices">
					<h2 id="grp-prices" class="grp-h2">Цены</h2>
					<p class="grp-text">Цена за одну мантию на всю аренду. Чем больше мантий в брони, тем дешевле каждая — по этой цене считаются все мантии заказа.</p>
					<?php echo gr_price_ladder_html(); // phpcs:ignore -- разметка из нашей функции ?>
					<p class="grp-text">В комплекте: мантия, шапочка с кисточкой и лента.</p>
				</section>

				<section class="grp-block" aria-labelledby="grp-deposit">
					<h2 id="grp-deposit" class="grp-h2">Залог</h2>
					<p class="grp-text">При самовывозе оставьте <strong>оригинал удостоверения личности</strong>. Если его нет — <strong>фото удостоверения и 10&nbsp;000&nbsp;₸</strong>. Всё вернём, когда вы сдадите мантии.</p>
				</section>
			</div>

			<!-- Квитанция штрафов -->
			<aside class="grp-receipt" aria-labelledby="grp-fees">
				<h2 id="grp-fees" class="grp-receipt-title">Берегите комплект</h2>
				<p class="grp-receipt-sub">Штраф за утерю, за 1 шт.</p>
				<ul class="grp-lines">
					<?php foreach ( gr_loss_fees() as $item => $sum ) : ?>
						<li><span><?php echo esc_html( $item ); ?></span><span><?php echo esc_html( gr_money( $sum ) ); ?></span></li>
					<?php endforeach; ?>
				</ul>

				<div class="grp-late">
					<p class="grp-receipt-sub">Просрочка возврата</p>
					<p class="grp-late-note">Рассчитывается отдельно в каждой ситуации. Не успеваете вернуть вовремя — напишите нам заранее.</p>
				</div>
			</aside>
		</div>

		<section class="grp-cta" aria-label="Перейти к бронированию">
			<p class="grp-cta-text">Всё понятно? Выберите мантии на свою дату.</p>
			<div class="grp-cta-actions">
				<a class="grp-btn grp-btn-gold" href="<?php echo esc_url( gr_catalog_url() ); ?>">Перейти в каталог <svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><path d="M5 12h14M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
				<a class="grp-btn grp-btn-line" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener">Задать вопрос в WhatsApp</a>
			</div>
		</section>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'gown_terms', 'gr_terms_shortcode' );

/* -------------------------------------------------------------------------
 * [gown_contacts] — Контакты
 * ---------------------------------------------------------------------- */

function gr_contacts_shortcode() {
	$c     = gr_contacts();
	$tel   = 'tel:' . preg_replace( '/[^\d+]/', '', $c['phone'] );
	$forms = get_posts( array( 'post_type' => 'wpcf7_contact_form', 'numberposts' => 1, 'fields' => 'ids' ) );

	ob_start();
	?>
	<div class="grp">
		<header class="grp-head">
			<h1 class="grp-title">Контакты</h1>
			<p class="grp-lead">Поможем подобрать мантию по размеру и цвету, ответим на вопросы об аренде. Быстрее всего — в WhatsApp.</p>
		</header>

		<div class="grp-contacts">
			<div class="grp-contact-list">
				<a class="grp-contact grp-contact-wa" href="<?php echo esc_url( gr_whatsapp_url() ); ?>" target="_blank" rel="noopener">
					<img class="grp-wa-icon" src="<?php echo esc_url( gr_landing_img( 'whatsapp' ) ); ?>" alt="" width="44" height="44">
					<span><small>WhatsApp — быстрее всего</small>Написать нам</span>
				</a>
				<a class="grp-contact" href="<?php echo esc_attr( $tel ); ?>">
					<?php echo gr_icon( 'phone' ); // phpcs:ignore ?>
					<span><small>Телефон</small><?php echo esc_html( $c['phone'] ); ?></span>
				</a>
				<a class="grp-contact" href="<?php echo esc_attr( 'mailto:' . $c['email'] ); ?>">
					<?php echo gr_icon( 'mail' ); // phpcs:ignore ?>
					<span><small>Email</small><?php echo esc_html( $c['email'] ); ?></span>
				</a>

				<div class="grp-contact grp-contact-static">
					<?php echo gr_icon( 'pin' ); // phpcs:ignore ?>
					<span>
						<small>Адрес пункта проката</small><?php echo esc_html( gr_pickup_address() ); ?>
						<a class="grp-map" href="<?php echo esc_url( gr_2gis_url() ); ?>" target="_blank" rel="noopener">Открыть в 2ГИС <?php echo gr_icon( 'arrow' ); // phpcs:ignore ?></a>
					</span>
				</div>

				<div class="grp-contact grp-contact-static">
					<?php echo gr_icon( 'clock' ); // phpcs:ignore ?>
					<span>
						<small>Часы работы</small>
						<dl class="grp-hours">
							<?php foreach ( $c['hours'] as $day => $time ) : ?>
								<div><dt><?php echo esc_html( $day ); ?></dt><dd><?php echo esc_html( $time ); ?></dd></div>
							<?php endforeach; ?>
						</dl>
						<?php if ( $c['hours_note'] ) : ?>
							<em><?php echo esc_html( $c['hours_note'] ); ?></em>
						<?php endif; ?>
					</span>
				</div>
			</div>

			<section class="grp-form" aria-labelledby="grp-form-title">
				<p class="grp-form-band" aria-hidden="true"><span>Сцена: вопрос</span><span>Перезвоним за 30 минут</span></p>
				<h2 id="grp-form-title" class="grp-h2">Напишите нам</h2>
				<p class="grp-text">Оставьте сообщение — перезвоним в течение 30 минут в рабочее время.</p>
				<?php
				if ( $forms ) {
					echo do_shortcode( '[contact-form-7 id="' . (int) $forms[0] . '"]' ); // phpcs:ignore -- разметка формы от Contact Form 7
				}
				?>
			</section>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'gown_contacts', 'gr_contacts_shortcode' );

/* -------------------------------------------------------------------------
 * [gown_privacy] — Политика конфиденциальности
 * ---------------------------------------------------------------------- */

function gr_privacy_shortcode() {
	$c    = gr_contacts();
	$name = get_bloginfo( 'name' );

	ob_start();
	?>
	<div class="grp grp-privacy">
		<header class="grp-head">
			<h1 class="grp-title">Политика конфиденциальности</h1>
			<p class="grp-lead">Какие данные мы получаем через сайт, зачем они нужны и как их удалить.</p>
		</header>

		<div class="grp-doc">
			<section>
				<h2 class="grp-h2">Кто обрабатывает данные</h2>
				<p class="grp-text">Прокат мантий «<?php echo esc_html( $name ); ?>», <?php echo esc_html( gr_pickup_address() ); ?>. Телефон и WhatsApp: <?php echo esc_html( $c['phone'] ); ?>, email: <?php echo esc_html( $c['email'] ); ?>.</p>
			</section>

			<section>
				<h2 class="grp-h2">Какие данные мы получаем</h2>
				<ul class="grp-list">
					<li>При бронировании — имя, телефон, email (по желанию), адрес доставки, комментарий, выбранные мантии и даты.</li>
					<li>Через форму «Напишите нам» — имя, телефон, email и текст сообщения.</li>
					<li>При получении мантий — удостоверение личности как залог: оригинал (возвращаем при сдаче мантий) или его фото.</li>
				</ul>
			</section>

			<section>
				<h2 class="grp-h2">Зачем</h2>
				<ul class="grp-list">
					<li>Чтобы подтвердить бронь, связаться с вами в WhatsApp или по телефону.</li>
					<li>Чтобы доставить и забрать мантии по указанному адресу.</li>
					<li>Чтобы ответить на ваш вопрос.</li>
				</ul>
				<p class="grp-text">Мы не используем данные для рассылок и не передаём их третьим лицам, кроме курьера, который везёт ваш заказ.</p>
			</section>

			<section>
				<h2 class="grp-h2">Как храним</h2>
				<p class="grp-text">Данные хранятся в базе сайта и доступны только администратору проката. Мы храним их, пока это нужно для выполнения брони и учёта возврата мантий. Фото удостоверения удаляем после возврата мантий и залога.</p>
			</section>

			<section>
				<h2 class="grp-h2">Ваши права</h2>
				<p class="grp-text">Вы можете узнать, какие данные о вас есть, попросить исправить или удалить их — напишите нам в WhatsApp или на почту. Отправляя бронь или сообщение, вы даёте согласие на обработку данных в соответствии с Законом Республики Казахстан «О персональных данных и их защите».</p>
			</section>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'gown_privacy', 'gr_privacy_shortcode' );

/* -------------------------------------------------------------------------
 * Contact Form 7: письма с формы «Напишите нам» — на email из настроек плагина
 * ---------------------------------------------------------------------- */

add_filter(
	'wpcf7_mail_components',
	function ( $components, $form, $mail ) {
		if ( 'mail' === $mail->name() ) { // только письмо нам, не автоответ клиенту
			$components['recipient'] = gr_contact_email();
		}
		return $components;
	},
	10,
	3
);
