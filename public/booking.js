/**
 * Каталог мантий с бронированием.
 *
 * Как это работает:
 * 1. Клиент выбирает даты → спрашиваем сервер, какие цвета и размеры есть в наличии
 *    (количество свободных мантий клиенту не показываем — только «есть / нет»).
 * 2. Клик по цвету открывает размеры, клик по размеру — поле количества.
 *    Можно выбрать сколько угодно цветов и размеров.
 * 3. Стоимость (аренда + доставка) пересчитывается сразу. Доставка — туда и обратно.
 * 4. Отправляем заявку; сервер перепроверяет всё ещё раз.
 */
(function () {
	'use strict';

	var cfg = window.grBooking;
	if (!cfg) return;

	// wp_localize_script передаёт числа строками — приводим к числам,
	// иначе «27000 + 2000» превратится в строку «270002000».
	cfg.pricePerDay = Number(cfg.pricePerDay);
	cfg.deliveryFee = Number(cfg.deliveryFee);
	cfg.freeFrom = Number(cfg.freeFrom);

	document.querySelectorAll('.gr-catalog').forEach(initCatalog);

	function initCatalog(root) {
		var form = root.querySelector('.gr-form');
		var dateFrom = form.elements.date_from;
		var dateTo = form.elements.date_to;
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

		/* ---------- Даты ---------- */
		[dateFrom, dateTo].forEach(function (el) {
			el.addEventListener('change', function () {
				if (el === dateFrom) {
					dateTo.min = dateFrom.value || cfg.today;
					if (dateTo.value && dateTo.value < dateFrom.value) dateTo.value = dateFrom.value;
				}
				loadAvailability();
			});
		});

		// Даты из ссылки с главной: /каталог/?from=2026-05-20&to=2026-05-22
		(function prefillFromUrl() {
			var params = new URLSearchParams(window.location.search);
			var re = /^\d{4}-\d{2}-\d{2}$/;
			var f = params.get('from'), t = params.get('to');
			if (f && t && re.test(f) && re.test(t) && f >= cfg.today && t >= f) {
				dateFrom.value = f;
				dateTo.min = f;
				dateTo.value = t;
				setTimeout(loadAvailability, 0);
			}
		})();

		function loadAvailability(quiet) {
			if (!quiet) { matrix = null; short = {}; }
			dateError = '';
			if (!dateFrom.value || !dateTo.value) { matrix = null; update(); return; }

			var id = ++requestId;
			if (!quiet) summary.innerHTML = '<p class="gr-muted">Проверяем наличие…</p>';
			post('gr_quote', { date_from: dateFrom.value, date_to: dateTo.value, items: JSON.stringify(itemList()) })
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
			var days = dayCount();
			var delivery = form.elements.delivery.value === '1';
			var free = qty >= cfg.freeFrom;
			var fee = free ? 0 : cfg.deliveryFee;

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
			form.elements.delivery_time.required = delivery;
			form.elements.address.required = delivery;
			root.querySelectorAll('.gr-fee').forEach(function (el) {
				el.textContent = free ? 'бесплатно' : money(cfg.deliveryFee);
			});
			root.querySelector('[data-date="from"]').textContent = dateFrom.value ? '— ' + fmtDate(dateFrom.value) : '— в день получения';

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
						+ (days ? '<span>' + money(days * cfg.pricePerDay * i.quantity) + '</span>' : '')
						+ (notThere ? '<em class="gr-error-text">столько нет на эти даты — уменьшите количество</em>' : '')
						+ '</li>';
				});
				html += '</ul>';

				if (days) {
					var rental = days * cfg.pricePerDay * qty;
					var total = rental + (delivery ? fee : 0);
					html += '<dl class="gr-totals">'
						+ row('Аренда: ' + qty + ' ' + plural(qty, 'мантия', 'мантии', 'мантий') + ' × ' + days + ' ' + plural(days, 'день', 'дня', 'дней'), money(rental))
						+ row(delivery ? 'Доставка и забор' : 'Самовывоз', delivery && fee ? money(fee) : 'бесплатно')
						+ row('<strong>Итого</strong>', '<strong>' + money(total) + '</strong>')
						+ '</dl>';
					if (!free && delivery) {
						html += '<p class="gr-muted">Закажите от ' + cfg.freeFrom + ' мантий — доставка будет бесплатной.</p>';
					}
				} else if (!dateError) {
					html += '<p class="gr-muted">Укажите даты, чтобы посчитать стоимость.</p>';
				}
			}
			summary.innerHTML = html;

			submit.disabled = !(list.length && matrix && allEnough);
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

		function dayCount() {
			if (!dateFrom.value || !dateTo.value || dateTo.value < dateFrom.value) return 0;
			return Math.round((Date.parse(dateTo.value) - Date.parse(dateFrom.value)) / 86400000) + 1;
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
					root.querySelector('.gr-success-text').textContent = res.data.message;
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

	function fmtDate(iso) {
		var p = iso.split('-');
		return p[2] + '.' + p[1] + '.' + p[0];
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
