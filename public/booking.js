/**
 * Каталог мантий с бронированием.
 *
 * Как это работает:
 * 1. Клиент выбирает дату и время начала (аренда 3 часа) → спрашиваем сервер, какие цвета
 *    и размеры есть в наличии (количество свободных мантий клиенту не показываем — только «есть / нет»).
 * 2. «Выбрать» под цветом открывает размеры, клик по размеру — поле количества.
 * 3. Цена за мантию зависит от количества во всей брони (лесенка цен), от 20 — одна бесплатно.
 *    Доставка — бесплатно и только от 100 мантий.
 * 4. Отправляем заявку; сервер перепроверяет всё ещё раз и возвращает данные для квитанции.
 */
(function () {
	'use strict';

	var cfg = window.grBooking;
	if (!cfg) return;

	// wp_localize_script передаёт числа строками — приводим к числам,
	// иначе «27000 + 2000» превратится в строку «270002000».
	cfg.freeFrom = Number(cfg.freeFrom);
	cfg.freeGownFrom = Number(cfg.freeGownFrom);
	var tiers = Object.keys(cfg.tiers).map(function (k) { return [Number(k), Number(cfg.tiers[k])]; })
		.sort(function (a, b) { return a[0] - b[0]; });

	function unitPrice(qty) {
		var price = 0;
		tiers.forEach(function (t) { if (qty >= t[0]) price = t[1]; });
		return price;
	}

	document.querySelectorAll('.gr-catalog').forEach(initCatalog);

	function initCatalog(root) {
		var form = root.querySelector('.gr-form');
		var dateEl = form.elements.date;
		var timeEl = form.elements.time;
		var submit = root.querySelector('.gr-submit');
		var summary = root.querySelector('.gr-summary-body');
		var errorsBox = root.querySelector('.gr-errors');
		var success = root.querySelector('.gr-success');

		var items = {};       // 'red|kids' → количество
		var matrix = null;    // есть ли в наличии на выбранные даты: matrix.red.kids = true / false
		var short = {};       // позиции, которых не хватает в выбранном количестве: { 'red|kids': true }
		var requoteTimer = 0;
		var dateError = '';
		var requestId = 0;

		/* ---------- Листание фото ---------- */
		root.querySelectorAll('.grc-photo').forEach(function (box) {
			var gallery = box.querySelector('.grc-gallery');
			var count = box.querySelector('.grc-count');
			var total = gallery.querySelectorAll('img').length;
			function go(dir) { gallery.scrollBy({ left: dir * gallery.clientWidth, behavior: 'smooth' }); }
			var prev = box.querySelector('.grc-nav-prev');
			var next = box.querySelector('.grc-nav-next');
			if (prev) prev.addEventListener('click', function () { go(-1); });
			if (next) next.addEventListener('click', function () { go(1); });
			gallery.addEventListener('keydown', function (e) {
				if (e.key === 'ArrowLeft') go(-1);
				if (e.key === 'ArrowRight') go(1);
			});
			if (count) gallery.addEventListener('scroll', function () {
				count.textContent = (Math.round(gallery.scrollLeft / gallery.clientWidth) + 1) + ' / ' + total;
			}, { passive: true });
		});

		/* ---------- Выбор цвета ---------- */
		root.querySelectorAll('.gr-card').forEach(function (card) {
			var colorBtn = card.querySelector('.gr-color-btn');
			var sizesBox = card.querySelector('.gr-sizes');

			colorBtn.addEventListener('click', function () {
				var open = colorBtn.getAttribute('aria-pressed') !== 'true';
				colorBtn.setAttribute('aria-pressed', String(open));
				card.classList.toggle('is-selected', open);
				sizesBox.hidden = !open;
				// Сняли цвет — убираем все его размеры из заказа.
				if (!open) {
					card.querySelectorAll('.gr-size').forEach(function (s) { setSize(card, s, false); });
				}
				update();
			});

			/* ---------- Выбор размера и количества ---------- */
			card.querySelectorAll('.gr-size').forEach(function (sizeEl) {
				var sizeBtn = sizeEl.querySelector('.gr-size-btn');
				var input = sizeEl.querySelector('.gr-qty-input');

				sizeBtn.addEventListener('click', function () {
					setSize(card, sizeEl, sizeBtn.getAttribute('aria-pressed') !== 'true');
					update();
					if (sizeBtn.getAttribute('aria-pressed') === 'true') input.focus();
				});
				sizeEl.querySelector('.gr-qty-minus').addEventListener('click', function () {
					input.value = Math.max(1, (parseInt(input.value, 10) || 1) - 1);
					syncQty(card, sizeEl);
				});
				sizeEl.querySelector('.gr-qty-plus').addEventListener('click', function () {
					input.value = Math.min(Number(input.max), (parseInt(input.value, 10) || 0) + 1);
					syncQty(card, sizeEl);
				});
				input.addEventListener('input', function () { syncQty(card, sizeEl); });
				input.addEventListener('blur', function () {
					if (!(parseInt(input.value, 10) >= 1)) input.value = 1;
					syncQty(card, sizeEl);
				});
			});
		});

		function key(card, sizeEl) {
			return card.dataset.color + '|' + sizeEl.dataset.size;
		}

		function setSize(card, sizeEl, on) {
			var btn = sizeEl.querySelector('.gr-size-btn');
			var qtyBox = sizeEl.querySelector('.gr-qty');
			btn.setAttribute('aria-pressed', String(on));
			sizeEl.classList.toggle('is-selected', on);
			qtyBox.hidden = !on;
			if (on) {
				items[key(card, sizeEl)] = parseInt(sizeEl.querySelector('.gr-qty-input').value, 10) || 1;
			} else {
				delete items[key(card, sizeEl)];
			}
			scheduleRequote();
		}

		function syncQty(card, sizeEl) {
			var n = parseInt(sizeEl.querySelector('.gr-qty-input').value, 10);
			if (n >= 1) items[key(card, sizeEl)] = n;
			scheduleRequote();
			update();
		}

		/* ---------- Дата и время ---------- */
		[dateEl, timeEl].forEach(function (el) { el.addEventListener('change', function () { loadAvailability(); }); });

		// Дата и время из ссылки с главной: /каталог/?date=2026-05-20&time=11:00
		(function prefillFromUrl() {
			var params = new URLSearchParams(window.location.search);
			var d = params.get('date') || params.get('from'), t = params.get('time');
			if (d && /^\d{4}-\d{2}-\d{2}$/.test(d) && d >= cfg.today) dateEl.value = d;
			if (t && timeEl.querySelector('option[value="' + t + '"]')) timeEl.value = t;
			if (dateEl.value && timeEl.value) setTimeout(loadAvailability, 0);
		})();

		function loadAvailability(quiet) {
			if (!quiet) { matrix = null; short = {}; }
			dateError = '';
			if (!dateEl.value || !timeEl.value) { matrix = null; update(); return; }

			var id = ++requestId;
			if (!quiet) summary.innerHTML = '<p class="gr-muted">Проверяем наличие…</p>';
			post('gr_quote', { date: dateEl.value, time: timeEl.value, items: JSON.stringify(itemList()) })
				.then(function (res) {
					if (id !== requestId) return; // устаревший ответ
					if (res.success) {
						matrix = res.data.matrix;
						short = {};
						(res.data.short || []).forEach(function (k) { short[k] = true; });
					} else {
						dateError = res.data.message;
					}
					update();
				})
				.catch(function () {
					if (id !== requestId) return;
					dateError = 'Не удалось проверить наличие. Обновите страницу.';
					update();
				});
		}

		/* Количество изменилось — тихо перепроверяем, хватает ли мантий. */
		function scheduleRequote() {
			clearTimeout(requoteTimer);
			if (!matrix) return;
			requoteTimer = setTimeout(function () { loadAvailability(true); }, 350);
		}

		/* ---------- Самовывоз или доставка ---------- */
		form.querySelectorAll('[name="delivery"]').forEach(function (r) {
			r.addEventListener('change', update);
		});

		/* ---------- Пересчёт всего на экране ---------- */
		function update() {
			var list = itemList();
			var qty = list.reduce(function (s, i) { return s + i.quantity; }, 0);
			var deliveryRadio = form.querySelector('[name="delivery"][value="1"]');
			deliveryRadio.disabled = qty < cfg.freeFrom;
			if (deliveryRadio.disabled && deliveryRadio.checked) form.querySelector('[name="delivery"][value="0"]').checked = true;
			var delivery = form.elements.delivery.value === '1';
			var unit = unitPrice(qty);
			var freeGowns = qty >= cfg.freeGownFrom ? 1 : 0;

			// Лесенка цен: подсветить текущую ступень.
			root.querySelectorAll('.gr-ladder-steps li').forEach(function (li) {
				var from = Number(li.dataset.from), to = Number(li.dataset.to);
				li.classList.toggle('is-current', qty >= from && (!to || qty <= to));
			});

			// Свободно / нет — у каждого размера.
			var allEnough = true;
			root.querySelectorAll('.gr-card').forEach(function (card) {
				card.querySelectorAll('.gr-size').forEach(function (sizeEl) {
					var freeEl = sizeEl.querySelector('.gr-free');
					var btn = sizeEl.querySelector('.gr-size-btn');
					var k = key(card, sizeEl);
					var n = matrix ? !!matrix[card.dataset.color][sizeEl.dataset.size] : null;
					var isShort = n !== null && !!items[k] && !!short[k];

					freeEl.textContent = n === null ? '' : (!n ? 'нет на эти даты' : (isShort ? 'столько нет' : 'в наличии'));
					sizeEl.classList.toggle('is-out', n === false);
					sizeEl.classList.toggle('is-short', isShort);
					btn.disabled = n === false && !items[k];
					if (isShort) allEnough = false;
				});
			});

			// Самовывоз / доставка: что показать, что обязательно, цена.
			root.querySelector('[data-show="self"]').hidden = delivery;
			root.querySelector('[data-show="delivery"]').hidden = !delivery;
			form.elements.address.required = delivery;

			// Итог заказа.
			var html = '';
			if (dateError) html += '<p class="gr-error-text">' + esc(dateError) + '</p>';
			if (!list.length) {
				html += '<p class="gr-muted">Выберите цвет и размер мантии.</p>';
			} else {
				html += '<ul class="gr-lines">';
				list.forEach(function (i) {
					var k = i.color + '|' + i.size;
					var notThere = matrix && (!matrix[i.color][i.size] || short[k]);
					html += '<li><span>' + esc(cfg.colors[i.color] + ', ' + cfg.sizes[i.size].toLowerCase() + ' × ' + i.quantity) + '</span>'
						+ '<span>' + money(unit * i.quantity) + '</span>'
						+ (notThere ? '<em class="gr-error-text">столько нет на это время — уменьшите количество</em>' : '')
						+ '</li>';
				});
				html += '</ul>';

				html += '<dl class="gr-totals">'
					+ row(qty + ' ' + plural(qty, 'мантия', 'мантии', 'мантий') + ' × ' + money(unit), money(unit * qty))
					+ (freeGowns ? row('Одна для организатора', '−' + money(unit)) : '')
					+ row(delivery ? 'Доставка' : 'Самовывоз', 'бесплатно')
					+ row('<strong>Итого</strong>', '<strong>' + money(unit * (qty - freeGowns)) + '</strong>')
					+ '</dl>';

				// Подсказка: сколько добавить до следующей ступени цены.
				var next = tiers.filter(function (t) { return t[0] > qty; })[0];
				if (next) {
					var add = next[0] - qty;
					html += '<p class="gr-nudge">Ещё ' + add + ' ' + plural(add, 'мантия', 'мантии', 'мантий') + ' — и каждая будет по ' + money(next[1]) + '</p>';
				} else if (!freeGowns) {
					var addG = cfg.freeGownFrom - qty;
					html += '<p class="gr-nudge">Ещё ' + addG + ' ' + plural(addG, 'мантия', 'мантии', 'мантий') + ' — и одна для организатора бесплатно</p>';
				}
				if (!dateEl.value || !timeEl.value) {
					html += '<p class="gr-muted">Выберите дату и время, чтобы проверить наличие.</p>';
				}
			}
			summary.innerHTML = html;

			submit.disabled = !(list.length && matrix && allEnough && unit);
		}

		function row(label, value) {
			return '<div><dt>' + label + '</dt><dd>' + value + '</dd></div>';
		}

		function itemList() {
			return Object.keys(items).map(function (k) {
				var p = k.split('|');
				return { color: p[0], size: p[1], quantity: items[k] };
			});
		}

		/* ---------- Отправка ---------- */
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			errorsBox.innerHTML = '';
			if (!form.reportValidity()) return;

			var data = {};
			new FormData(form).forEach(function (v, k) { data[k] = v; });
			data.items = JSON.stringify(itemList());

			submit.disabled = true;
			submit.textContent = 'Отправляем…';

			post('gr_submit_booking', data).then(function (res) {
				if (res.success) {
					root.querySelector('.gr-success-text').innerHTML = receipt(res.data);
					form.hidden = true;
					success.hidden = false;
					success.scrollIntoView({ behavior: 'smooth', block: 'center' });
				} else {
					showErrors(res.data.errors || ['Ошибка. Попробуйте ещё раз.']);
					loadAvailability(); // наличие могло измениться
				}
			}).catch(function () {
				showErrors(['Не удалось отправить заявку. Проверьте интернет и попробуйте снова.']);
			}).finally(function () {
				submit.textContent = 'Забронировать';
				update();
			});
		});

		root.querySelector('.gr-again').addEventListener('click', function () {
			form.reset();
			items = {};
			matrix = null;
			short = {};
			root.querySelectorAll('.gr-card').forEach(function (card) {
				card.classList.remove('is-selected');
				card.querySelector('.gr-color-btn').setAttribute('aria-pressed', 'false');
				card.querySelector('.gr-sizes').hidden = true;
				card.querySelectorAll('.gr-size').forEach(function (s) { setSize(card, s, false); });
			});
			form.hidden = false;
			success.hidden = true;
			update();
		});

		function receipt(d) {
			if (!d.id) return '<p>' + esc(d.message || 'Спасибо!') + '</p>';
			return '<p class="gr-success-num">Бронь № ' + d.id + '</p>'
				+ '<dl class="gr-success-list">'
				+ '<div><dt>Когда</dt><dd>' + esc(d.date) + '<br>' + esc(d.time) + '</dd></div>'
				+ '<div><dt>Мантии</dt><dd>' + d.items.map(function (i) { return esc(i.color + ', ' + i.size.toLowerCase() + ' — ' + i.quantity + ' шт.'); }).join('<br>') + '</dd></div>'
				+ '<div><dt>Цена</dt><dd>' + esc(d.quantity + ' × ' + d.unit_price) + (d.free_gowns ? '<br>одна для организатора бесплатно' : '') + '</dd></div>'
				+ '<div><dt>' + (d.delivery ? 'Доставка' : 'Самовывоз') + '</dt><dd>' + esc(d.address) + '</dd></div>'
				+ '<div class="gr-success-total"><dt>Итого</dt><dd>' + esc(d.total) + '</dd></div>'
				+ '</dl>'
				+ '<p class="gr-success-next">Мы напишем вам в WhatsApp на номер ' + esc(d.phone) + ', чтобы подтвердить бронь.'
				+ (d.delivery ? '' : ' При получении возьмите удостоверение личности — это залог.') + '</p>';
		}

		function showErrors(list) {
			errorsBox.innerHTML = '<ul>' + list.map(function (m) { return '<li>' + esc(m) + '</li>'; }).join('') + '</ul>';
			errorsBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}

		update();
	}

	/* ---------- Общие помощники ---------- */

	/** POST-запрос к admin-ajax.php. Возвращает Promise с JSON-ответом. */
	function post(action, data) {
		var body = new URLSearchParams(data);
		body.append('action', action);
		body.append('nonce', cfg.nonce);
		return fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); });
	}

	function money(n) {
		return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ₸';
	}


	/** 1 мантия, 2 мантии, 5 мантий. */
	function plural(n, one, few, many) {
		var a = n % 10, b = n % 100;
		if (a === 1 && b !== 11) return one;
		if (a >= 2 && a <= 4 && (b < 12 || b > 14)) return few;
		return many;
	}

	function esc(s) {
		var div = document.createElement('div');
		div.textContent = s;
		return div.innerHTML;
	}
})();
