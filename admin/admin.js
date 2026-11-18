/**
 * Админка проката мантий:
 *  - смена статуса прямо в списке броней (сохраняется сразу);
 *  - добавление/удаление позиций в форме брони;
 *  - показ полей времени доставки;
 *  - выбор фото мантий в настройках.
 */
(function () {
	'use strict';

	var cfg = window.grAdmin || {};

	/* ---------- Быстрая смена статуса ---------- */
	document.querySelectorAll('.gr-status-select').forEach(function (select) {
		select.addEventListener('change', function () {
			var row = select.closest('tr');
			var result = select.parentElement.querySelector('.gr-status-result');
			var prev = select.dataset.prev;
			var next = select.value;

			select.disabled = true;
			result.textContent = '…';
			result.className = 'gr-status-result';

			var body = new URLSearchParams({ action: 'gr_set_status', nonce: cfg.nonce, id: select.dataset.id, status: next });
			fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (res.success) {
						select.dataset.prev = next;
						row.className = row.className.replace(/gr-row-\S+/, 'gr-row-' + next);
						result.textContent = '✓';
						result.classList.add('is-ok');
						setTimeout(function () { result.textContent = ''; }, 2000);
					} else {
						select.value = prev; // вернуть как было
						result.textContent = '';
						alert(res.data && res.data.message ? res.data.message : 'Не удалось сменить статус.');
					}
				})
				.catch(function () {
					select.value = prev;
					result.textContent = '';
					alert('Ошибка связи с сервером. Обновите страницу.');
				})
				.finally(function () { select.disabled = false; });
		});
	});

	/* ---------- Позиции брони ---------- */
	var itemsBox = document.querySelector('.gr-items');
	var template = document.getElementById('gr-item-template');
	var addBtn = document.querySelector('.gr-add-item');
	var counter = itemsBox ? itemsBox.children.length : 0;

	if (itemsBox && template && addBtn) {
		addBtn.addEventListener('click', function () {
			var html = template.innerHTML.replace(/__i__/g, String(counter++));
			itemsBox.insertAdjacentHTML('beforeend', html);
		});
		itemsBox.addEventListener('click', function (e) {
			if (!e.target.classList.contains('gr-remove-item')) return;
			var rows = itemsBox.querySelectorAll('.gr-item-row');
			if (rows.length > 1) {
				e.target.closest('.gr-item-row').remove();
			} else {
				// последнюю строку не удаляем, а очищаем
				rows[0].querySelectorAll('select').forEach(function (s) { s.value = ''; });
			}
		});
	}

	/* ---------- Чекбоксы доставки показывают поле времени ---------- */
	document.querySelectorAll('.gr-toggle').forEach(function (box) {
		var target = document.querySelector(box.dataset.target);
		if (!target) return;
		box.addEventListener('change', function () { target.hidden = !box.checked; });
	});

	/* ---------- Фото мантий (медиатека WordPress) ---------- */
	document.querySelectorAll('.gr-photo-field').forEach(function (field) {
		var input = field.querySelector('input[type="hidden"]');
		var preview = field.querySelector('.gr-photo-preview');
		var clear = field.querySelector('.gr-clear-photo');

		field.querySelector('.gr-pick-photo').addEventListener('click', function () {
			if (!window.wp || !wp.media) return;
			var frame = wp.media({ title: 'Фото мантии', button: { text: 'Выбрать' }, library: { type: 'image' }, multiple: false });
			frame.on('select', function () {
				var img = frame.state().get('selection').first().toJSON();
				var url = img.sizes && img.sizes.medium ? img.sizes.medium.url : img.url;
				input.value = img.id;
				preview.innerHTML = '';
				var el = document.createElement('img');
				el.src = url;
				el.alt = '';
				preview.appendChild(el);
				clear.hidden = false;
			});
			frame.open();
		});

		clear.addEventListener('click', function () {
			input.value = '0';
			preview.innerHTML = '<span>нет фото</span>';
			clear.hidden = true;
		});
	});
})();
