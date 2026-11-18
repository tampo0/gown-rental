/* Шапка: тень при прокрутке, «магнитные» кнопки, рамка автофокуса, мобильное меню. */
(function () {
	var header = document.querySelector('[data-grh]');
	if (!header) return;

	var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

	/* Тонкая линия под шапкой после начала прокрутки */
	function onScroll() { header.classList.toggle('is-scrolled', window.scrollY > 8); }
	onScroll();
	window.addEventListener('scroll', onScroll, { passive: true });

	/* Магнит: чем ближе курсор, тем крупнее кнопка и тем сильнее она тянется к нему */
	if (finePointer && !reduce) {
		var R = 110; // радиус «притяжения», px
		var items = Array.prototype.map.call(header.querySelectorAll('[data-grh-mag]'), function (el) {
			return { el: el, k: el.classList.contains('grh-btn') ? 0.04 : 0.06, s: 1, x: 0, y: 0 };
		});
		var px = -1e4, py = -1e4, running = false;

		var frame = function () {
			var moving = false;
			items.forEach(function (it) {
				var r = it.el.getBoundingClientRect();
				// Прямоугольник без текущего сдвига/масштаба
				var cx = r.left + r.width / 2 - it.x, cy = r.top + r.height / 2 - it.y;
				var w = r.width / it.s / 2, h = r.height / it.s / 2;
				var dx = Math.max(Math.abs(px - cx) - w, 0), dy = Math.max(Math.abs(py - cy) - h, 0);
				var t = Math.max(0, 1 - Math.sqrt(dx * dx + dy * dy) / R);
				t = t * t;
				var ts = 1 + it.k * t;
				var tx = Math.max(-3, Math.min(3, (px - cx) * 0.1 * t));
				var ty = Math.max(-2, Math.min(2, (py - cy) * 0.1 * t));
				it.s += (ts - it.s) * 0.2;
				it.x += (tx - it.x) * 0.2;
				it.y += (ty - it.y) * 0.2;
				if (Math.abs(ts - it.s) > 0.001 || Math.abs(tx - it.x) > 0.05 || Math.abs(ty - it.y) > 0.05) moving = true;
				it.el.style.setProperty('--s', it.s.toFixed(4));
				it.el.style.setProperty('--mx', it.x.toFixed(2) + 'px');
				it.el.style.setProperty('--my', it.y.toFixed(2) + 'px');
			});
			running = moving;
			if (moving) requestAnimationFrame(frame);
		};
		var kick = function () { if (!running) { running = true; requestAnimationFrame(frame); } };

		header.addEventListener('pointermove', function (e) { px = e.clientX; py = e.clientY; kick(); });
		header.addEventListener('pointerleave', function () { px = py = -1e4; kick(); });
	}

	/* Рамка автофокуса: стоит на текущей странице, переезжает к пункту под курсором */
	var nav = header.querySelector('.grh-nav');
	var focus = header.querySelector('[data-grh-focus]');
	if (nav && focus) {
		var current = nav.querySelector('.grh-link[aria-current="page"]');
		var place = function (link, lock) {
			if (!link) { focus.classList.remove('is-on', 'is-lock', 'is-rest'); return; }
			focus.style.setProperty('--fx', link.offsetLeft + 'px');
			focus.style.setProperty('--fy', link.offsetTop + 'px');
			focus.style.setProperty('--fw', link.offsetWidth + 'px');
			focus.style.setProperty('--fh', link.offsetHeight + 'px');
			focus.classList.add('is-on');
			focus.classList.toggle('is-lock', lock);
			focus.classList.toggle('is-rest', !lock);
		};
		var rest = function () { place(current, false); };
		var firstRest = function () {
			focus.style.transition = 'none';
			rest();
			focus.getBoundingClientRect(); // применить позицию сразу
			focus.style.transition = '';
		};
		nav.querySelectorAll('.grh-link').forEach(function (link) {
			link.addEventListener('mouseenter', function () { place(link, true); });
			link.addEventListener('focus', function () { place(link, true); });
		});
		nav.addEventListener('mouseleave', rest);
		nav.addEventListener('focusout', function (e) { if (!nav.contains(e.relatedTarget)) rest(); });
		// Шрифты меняют ширину пунктов — ставим рамку после их загрузки
		// Шрифты подгружаются позже и меняют ширину пунктов — пересчитываем рамку, когда меню меняет размер.
		var calm = function () { if (!nav.matches(':hover') && !nav.contains(document.activeElement)) firstRest(); };
		if (window.ResizeObserver) new ResizeObserver(calm).observe(nav.querySelector('.grh-list'));
		else window.addEventListener('resize', calm);
		window.addEventListener('load', calm);
	}

	/* Мобильное меню на весь экран */
	var toggle = header.querySelector('[data-grh-toggle]');
	var sheet = header.querySelector('[data-grh-sheet]');
	if (!toggle || !sheet) return;

	var open = function () {
		header.style.setProperty('--grh-top', header.getBoundingClientRect().bottom + 'px');
		sheet.hidden = false;
		toggle.setAttribute('aria-expanded', 'true');
		document.documentElement.classList.add('grh-locked');
		requestAnimationFrame(function () { requestAnimationFrame(function () { sheet.classList.add('is-open'); }); });
		var first = sheet.querySelector('a');
		if (first) first.focus({ preventScroll: true });
	};
	var close = function (returnFocus) {
		if (sheet.hidden) return;
		sheet.classList.remove('is-open');
		toggle.setAttribute('aria-expanded', 'false');
		document.documentElement.classList.remove('grh-locked');
		setTimeout(function () { sheet.hidden = true; }, reduce ? 0 : 500);
		if (returnFocus) toggle.focus();
	};

	toggle.addEventListener('click', function () { if (sheet.hidden) open(); else close(false); });
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(true); });
	sheet.addEventListener('click', function (e) { if (e.target.closest('a')) close(false); });
	window.addEventListener('resize', function () { if (window.innerWidth > 1100) close(false); });
})();
