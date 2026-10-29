<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * Geographical maps page (Geomap layers module): the Zabbix 7.2 behaviour, plus Google map types and the
 * Google Maps API key test.
 *
 * @var CView $this
 */
?>

<script>
	const view = {
		tile_url: null,
		attribution: null,
		max_zoom: null,
		tile_providers: {},
		google_types: {},
		defaults: {},

		init({tile_providers, google_types}) {
			this.tile_url = document.getElementById('geomaps_tile_url');
			this.attribution = document.getElementById('geomaps_attribution');
			this.max_zoom = document.getElementById('geomaps_max_zoom');

			this.tile_providers = tile_providers;
			this.google_types = google_types;
			this.defaults = {
				geomaps_tile_url: '',
				geomaps_max_zoom: '0'
			};

			document.querySelector('[name="geomaps_tile_provider"]')
				.addEventListener('change', this.events.tileProviderChange);

			document.getElementById('geomaps-form').addEventListener('submit', this.events.submit);
			document.getElementById('google_key_test').addEventListener('click', this.events.testKey);
		},

		toggleRow(field, show) {
			const form_field = field.closest('.form-field');

			form_field.classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !show);
			form_field.previousElementSibling.classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !show);
		},

		events: {
			tileProviderChange(e) {
				const is_google = e.target.value in view.google_types;
				const is_other = e.target.value === '';

				view.tile_url.readOnly = !is_other;
				view.max_zoom.readOnly = !is_other;

				for (const field of [view.tile_url, view.max_zoom]) {
					if (is_other) {
						field.removeAttribute('tabIndex');
					}
					else {
						field.tabIndex = -1;
					}
				}

				view.toggleRow(view.tile_url, !is_google);
				view.toggleRow(view.attribution, is_other);

				const data = is_google
					? {geomaps_tile_url: '', geomaps_max_zoom: view.google_types[e.target.value].geomaps_max_zoom}
					: view.tile_providers[e.target.value] || view.defaults;

				view.tile_url.value = data.geomaps_tile_url;
				view.max_zoom.value = data.geomaps_max_zoom;
				view.attribution.value = '';
			},

			submit() {
				view.tile_url.value = view.tile_url.value.trim();
				view.attribution.value = view.attribution.value.trim();
			},

			testKey() {
				const key = document.getElementById('google_key').value.trim();
				const result = document.getElementById('google_key_result');
				const show = (text, ok) => {
					result.textContent = text;
					result.className = ok ? '<?= ZBX_STYLE_GREEN ?>' : '<?= ZBX_STYLE_RED ?>';
				};

				if (key === '') {
					show(<?= json_encode(_('Enter the API key first.')) ?>, false);

					return;
				}

				result.textContent = <?= json_encode(_('Checking...')) ?>;
				result.className = '';

				// From this browser, like the geomap widgets, so the key's website restriction is tested too.
				fetch('https://tile.googleapis.com/v1/createSession?key=' + encodeURIComponent(key), {
					method: 'POST',
					headers: {'Content-Type': 'application/json'},
					body: JSON.stringify({mapType: 'roadmap', language: 'en'})
				})
					.then(r => r.json().then(data => ({ok: r.ok && 'session' in data, data})))
					.then(({ok, data}) => {
						if (ok) {
							show(<?= json_encode(_('The key works. Click Update to save it.')) ?>, true);
						}
						else {
							show(<?= json_encode(_('Google refused the key:')) ?> + ' '
								+ ((data.error && data.error.message) || <?= json_encode(_('unknown error')) ?>), false
							);
						}
					})
					.catch(() => show(<?= json_encode(_('Could not reach Google from this browser.')) ?>, false));
			}
		}
	};
</script>
