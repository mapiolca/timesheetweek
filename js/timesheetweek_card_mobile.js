(function (window, document) {
	'use strict';

	function ready(callback) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', callback);
		} else {
			callback();
		}
	}

	ready(function () {
		var root = document.getElementById('timesheetweek-mobile-card');
		if (!root) return;
		var navigationAnchor = root.querySelector('.tw-day-navigation-anchor');
		var dayNavigation = root.querySelector('.tw-day-navigation');
		var navigationFrame = null;

		function updatePinnedNavigation() {
			navigationFrame = null;
			if (!navigationAnchor || !dayNavigation) return;
			var anchorRect = navigationAnchor.getBoundingClientRect();
			var mustBeFixed = anchorRect.top <= 0;
			if (mustBeFixed) {
				var navigationStyle = window.getComputedStyle(dayNavigation);
				var marginBottom = parseFloat(navigationStyle.marginBottom) || 0;
				navigationAnchor.style.height = (dayNavigation.offsetHeight + marginBottom) + 'px';
				dayNavigation.style.left = Math.max(0, anchorRect.left) + 'px';
				dayNavigation.style.width = anchorRect.width + 'px';
				dayNavigation.classList.add('tw-day-navigation-fixed');
			} else {
				dayNavigation.classList.remove('tw-day-navigation-fixed');
				dayNavigation.style.left = '';
				dayNavigation.style.width = '';
				navigationAnchor.style.height = '';
			}
		}

		function requestPinnedNavigationUpdate() {
			if (navigationFrame !== null) return;
			navigationFrame = window.requestAnimationFrame(updatePinnedNavigation);
		}

		window.addEventListener('scroll', requestPinnedNavigationUpdate);
		window.addEventListener('resize', requestPinnedNavigationUpdate);
		requestPinnedNavigationUpdate();

		function activateDay(day, activeButton) {
			Array.prototype.forEach.call(root.querySelectorAll('[data-tw-day]'), function (item) {
				var active = item === activeButton;
				item.classList.toggle('tw-day-active', active);
				item.setAttribute('aria-pressed', active ? 'true' : 'false');
			});
			Array.prototype.forEach.call(root.querySelectorAll('[data-tw-panel]'), function (panel) {
				var hidden = panel.getAttribute('data-tw-panel') !== day;
				panel.classList.toggle('tw-day-hidden', hidden);
				panel.hidden = hidden;
			});
		}

		// Day navigation must remain available even if autosave cannot be initialized.
		Array.prototype.forEach.call(root.querySelectorAll('[data-tw-day]'), function (button) {
			button.addEventListener('click', function () {
				activateDay(button.getAttribute('data-tw-day'), button);
			});
		});

		var form = document.getElementById('timesheetweek-mobile-form');
		if (!form) return;

		var config;
		try { config = JSON.parse(root.getAttribute('data-config') || '{}'); } catch (e) { return; }
		var status = document.getElementById('tw-autosave-status');
		var restoreBox = document.getElementById('tw-restore-message');
		var timer = null;
		var saving = false;
		var queued = false;
		var dirtyFields = {};

		function setStatus(key, isError) {
			if (!status) return;
			status.textContent = (config.messages && config.messages[key]) || '';
			status.classList.toggle('tw-state-error', !!isError);
		}

		function editableFields() {
			return form.querySelectorAll('input.hourinput, select.daily-rate-select, select.tw-zone-select, input.mealbox');
		}

		function snapshot() {
			var data = {};
			Array.prototype.forEach.call(editableFields(), function (field) {
				data[field.name] = field.type === 'checkbox' ? !!field.checked : field.value;
			});
			return data;
		}

		function storeLocal() {
			if (!config.storageKey) return;
			try {
				window.localStorage.setItem(config.storageKey, JSON.stringify({savedAt: Date.now(), values: snapshot()}));
			} catch (e) { /* Private browsing or quota: server autosave still works. */ }
		}

		function clearLocal() {
			try { window.localStorage.removeItem(config.storageKey); } catch (e) { /* no-op */ }
		}

		function applyValues(values) {
			Object.keys(values || {}).forEach(function (name) {
				var field = form.elements[name];
				if (!field || field.disabled) return;
				if (field.type === 'checkbox') field.checked = !!values[name];
				else field.value = values[name];
				schedule(field);
			});
		}

		function parseHours(value) {
			var raw = String(value || '').trim().replace(',', '.');
			if (!raw) return 0;
			if (raw.indexOf(':') < 0) return parseFloat(raw) || 0;
			var parts = raw.split(':');
			return (parseInt(parts[0], 10) || 0) + ((parseInt(parts[1], 10) || 0) / 60);
		}

		function formatHours(value) {
			var hours = Math.floor(value);
			var minutes = Math.round((value - hours) * 60);
			if (minutes === 60) { hours++; minutes = 0; }
			return (hours < 10 ? '0' : '') + hours + ':' + (minutes < 10 ? '0' : '') + minutes;
		}

		function updateTotal() {
			var total = 0;
			Array.prototype.forEach.call(form.querySelectorAll('input.hourinput, select.daily-rate-select'), function (field) {
				if (field.classList.contains('daily-rate-select')) {
					total += parseFloat((config.dailyRateHours || {})[field.value]) || 0;
				} else total += parseHours(field.value);
			});
			var target = document.getElementById('tw-mobile-grand-total');
			if (target) target.textContent = config.dailyRate ? (Math.round((total / 8) * 100) / 100).toFixed(2) : formatHours(total);
		}

		function relatedValue(prefix, day) {
			var field = form.elements[prefix + '_' + day];
			if (!field) return prefix === 'meal' ? false : '';
			return field.type === 'checkbox' ? !!field.checked : field.value;
		}

		function buildChange(field) {
			var match = /^(hours|daily)_(\d+)_(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)$/.exec(field.name);
			if (!match) {
				var settingMatch = /^(zone|meal)_(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)$/.exec(field.name);
				if (!settingMatch) return null;
				return {taskId: 0, day: settingMatch[2], value: '', dailyRate: !!config.dailyRate, zone: relatedValue('zone', settingMatch[2]), meal: relatedValue('meal', settingMatch[2]), settingsOnly: true};
			}
			return {taskId: parseInt(match[2], 10), day: match[3], value: field.value, dailyRate: match[1] === 'daily', zone: relatedValue('zone', match[3]), meal: relatedValue('meal', match[3])};
		}

		function send() {
			var failed = false;
			var dirtyNames = Object.keys(dirtyFields);
			if (!dirtyNames.length || saving || !config.editable) return Promise.resolve();
			var fieldName = dirtyNames[0];
			var fieldVersion = dirtyFields[fieldName];
			var field = form.elements[fieldName];
			if (!field) { delete dirtyFields[fieldName]; return send(); }
			var change = buildChange(field);
			if (!change) { delete dirtyFields[fieldName]; return send(); }
			saving = true;
			queued = false;
			setStatus('saving', false);
			var body = new URLSearchParams();
			body.set('token', config.token);
			body.set('action', 'autosave');
			body.set('id', config.id);
			body.set('revision', config.revision || 0);
			body.set('change', JSON.stringify(change));
			return window.fetch(config.endpoint, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest'}, body: body.toString()})
				.then(function (response) { return response.json().then(function (json) { return {ok: response.ok, json: json}; }); })
				.then(function (result) {
					if (!result.ok || !result.json.success) throw new Error(result.json.message || 'autosave');
					config.revision = result.json.revision || config.revision;
					if (dirtyFields[fieldName] === fieldVersion) delete dirtyFields[fieldName];
					if (!Object.keys(dirtyFields).length) {
						setStatus('saved', false);
						clearLocal();
					}
				})
				.catch(function (error) {
					failed = true;
					queued = false;
					if (window.navigator.onLine && error && error.message && error.message !== 'autosave' && status) {
						status.textContent = error.message;
						status.classList.add('tw-state-error');
					} else {
						setStatus(window.navigator.onLine ? 'error' : 'offline', true);
					}
				})
				.then(function () {
					saving = false;
					if (!failed && (queued || Object.keys(dirtyFields).length)) return send();
				});
		}

		function schedule(field) {
			dirtyFields[field.name] = (dirtyFields[field.name] || 0) + 1;
			queued = saving;
			storeLocal();
			updateTotal();
			setStatus('pending', false);
			window.clearTimeout(timer);
			timer = window.setTimeout(send, 900);
		}

		Array.prototype.forEach.call(editableFields(), function (field) {
			field.addEventListener(field.tagName === 'INPUT' && field.type === 'text' ? 'input' : 'change', function () { schedule(field); });
		});

		try {
			var localDraft = JSON.parse(window.localStorage.getItem(config.storageKey) || 'null');
			if (localDraft && localDraft.values && restoreBox && config.editable) restoreBox.hidden = false;
		} catch (e) { /* no-op */ }

		if (restoreBox) {
			restoreBox.addEventListener('click', function (event) {
				var choice = event.target.getAttribute('data-tw-restore');
				if (!choice) return;
				if (choice === 'yes') {
					try { var draft = JSON.parse(window.localStorage.getItem(config.storageKey) || 'null'); if (draft) applyValues(draft.values); } catch (e) { /* no-op */ }
					setStatus('pending', false);
				}
				if (choice === 'no') clearLocal();
				restoreBox.hidden = true;
			});
		}

		form.addEventListener('submit', function () { window.clearTimeout(timer); });
		Array.prototype.forEach.call(document.querySelectorAll('.tabsAction a'), function (link) {
			link.addEventListener('click', function (event) {
				if (!Object.keys(dirtyFields).length) return;
				event.preventDefault();
				var destination = link.href;
				send().then(function () {
					if (!Object.keys(dirtyFields).length) window.location.href = destination;
				});
			});
		});
		window.addEventListener('beforeunload', function () {
			if (Object.keys(dirtyFields).length) storeLocal();
		});
		window.addEventListener('online', function () { if (Object.keys(dirtyFields).length) send(); });
		updateTotal();
	});
})(window, document);
