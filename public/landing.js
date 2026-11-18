/* Главная: проверка наличия по датам прямо на первом экране. */
(function () {
	'use strict';

	var cfg = window.grLanding;
	var root = document.getElementById('grl');
	if (!cfg || !root) return;

	var form = root.querySelector('.grl-slate');
	var from = form.elements.from;
	var to = form.elements.to;
	var hint = root.querySelector('[data-grl-hint]');
	var counts = root.querySelector('[data-grl-counts]');
	var cta = root.querySelector('[data-grl-cta]');
	var ctaDates = root.querySelector('[data-grl-cta-dates]');
	var defaultHint = hint.textContent;
	var requestId = 0;

	from.min = cfg.today;
	to.min = cfg.today;

	function fmt(iso) {
		var p = iso.split('-');
		return p[2] + '.' + p[1];
	}


	function catalogUrl() {
		if (!from.value || !to.value) return cfg.catalogUrl;
		var sep = cfg.catalogUrl.indexOf('?') === -1 ? '?' : '&';
		return cfg.catalogUrl + sep + 'from=' + encodeURIComponent(from.value) + '&to=' + encodeURIComponent(to.value);
	}

	function syncLinks() {
		var url = catalogUrl();
		cta.href = url;
		ctaDates.textContent = from.value && to.value ? ' на ' + fmt(from.value) + '–' + fmt(to.value) : '';
		root.querySelectorAll('[data-grl-color-link], [data-grl-cta-plain]').forEach(function (a) { a.href = url; });
	}

	function showHint(text, isError) {
		counts.hidden = true;
		hint.hidden = false;
		hint.textContent = text;
		hint.classList.toggle('is-error', !!isError);
		root.querySelectorAll('[data-grl-frame-free]').forEach(function (el) { el.textContent = ''; });
	}

	function showCounts(matrix) {
		// Сервер говорит только «есть / нет» — количество клиенту не показываем.
		Object.keys(matrix).forEach(function (color) {
			var has = Object.keys(matrix[color]).some(function (size) { return !!matrix[color][size]; });
			var num = root.querySelector('[data-grl-count="' + color + '"]');
			if (num) {
				num.textContent = has ? ' — есть' : ' — нет на эти даты';
				num.closest('li').classList.toggle('is-out', !has);
			}
			var edge = root.querySelector('[data-grl-frame-free="' + color + '"]');
			if (edge) edge.textContent = has ? 'в наличии' : 'нет на эти даты';
		});
		hint.hidden = true;
		counts.hidden = false;
		counts.classList.remove('is-fresh');
		void counts.offsetWidth; // перезапуск анимации
		counts.classList.add('is-fresh');
	}

	function check() {
		syncLinks();
		from.removeAttribute('aria-invalid');
		to.removeAttribute('aria-invalid');

		if (!from.value || !to.value) {
			showHint(defaultHint);
			return;
		}
		if (to.value < from.value) {
			to.setAttribute('aria-invalid', 'true');
			showHint('День возврата не может быть раньше дня получения.', true);
			return;
		}

		var id = ++requestId;
		showHint('Проверяем склад…');

		var body = new FormData();
		body.append('action', 'gr_quote');
		body.append('nonce', cfg.nonce);
		body.append('date_from', from.value);
		body.append('date_to', to.value);

		fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (id !== requestId) return;
				if (res.success) showCounts(res.data.matrix);
				else showHint(res.data && res.data.message ? res.data.message : 'Не удалось проверить даты.', true);
			})
			.catch(function () {
				if (id !== requestId) return;
				showHint('Не удалось проверить наличие. Обновите страницу.', true);
			})
			;
	}

	from.addEventListener('change', function () {
		to.min = from.value || cfg.today;
		if (to.value && from.value && to.value < from.value) to.value = from.value;
		check();
	});
	to.addEventListener('change', check);

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		if (!from.value) { from.focus(); from.setAttribute('aria-invalid', 'true'); return; }
		if (!to.value) { to.focus(); to.setAttribute('aria-invalid', 'true'); return; }
		check();
	});

	syncLinks();
})();
