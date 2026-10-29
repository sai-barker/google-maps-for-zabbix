/*
 * Geomap layers: a Layers button on every Geomap widget to switch the background map.
 *
 * No Zabbix core files are changed. Leaflet is only loaded on pages with dashboards (before module
 * assets), so on other pages this does nothing. A Leaflet init hook catches each new map; the first
 * tile layer the widget adds (the map from Administration > General > Geographical maps) becomes the
 * "default" entry, the rest come from zabbix.php?action=geomaplayers.list. The pick is remembered per
 * browser and applied to every geomap on the page.
 *
 * Google layers (Map Tiles API) need a session: the browser creates one per map type with the key from
 * the list, keeps it in localStorage until a day before it expires, and renews it on open pages. While a
 * Google layer is on, the map shows Google's logo and the copyright line Google returns for the view.
 *
 * A Google default map (Administration > General > Geographical maps) is stored by Zabbix as a marker URL;
 * its tile layer loads nothing until the session is ready, then gets the real Google URL.
 */
(function() {
	'use strict';

	if (typeof L === 'undefined' || !L.Map || !L.TileLayer) {
		return;
	}

	const STORAGE_KEY = 'geomaplayers.layer';
	const SESSION_KEY = 'geomaplayers.google.';
	const DEFAULT_ID = 'default';
	const MAX_ZOOM = 19;
	const GOOGLE_API = 'https://tile.googleapis.com/v1/';
	const DAY = 86400000;
	const GOOGLE_MARKER = GOOGLE_API + '2dtiles/{z}/{x}/{y}?geomaplayers=';
	const BLANK_TILE = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

	const maps = new Set();
	const sessions = {};
	let list_promise = null;

	function loadList() {
		if (list_promise === null) {
			list_promise = fetch('zabbix.php?action=geomaplayers.list', {credentials: 'same-origin'})
				.then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)))
				.then(data => ({button: !!data.button, layers: Array.isArray(data.layers) ? data.layers : []}))
				.catch(e => {
					console.warn('Geomap layers: could not load the layer list.', e);
					list_promise = null;

					return {button: false, layers: []};
				});
		}

		return list_promise;
	}

	function storageGet(key) {
		try {
			return localStorage.getItem(key);
		}
		catch (e) {
			return null;
		}
	}

	function storageSet(key, value) {
		try {
			if (value === null) {
				localStorage.removeItem(key);
			}
			else {
				localStorage.setItem(key, value);
			}
		}
		catch (e) {}
	}

	function sameUrl(a, b) {
		const clean = url => String(url).replace(/^https?:/, '').replace(/\{s\}\./, '').replace(/\/+$/, '');

		return clean(a) === clean(b);
	}

	function escapeHtml(text) {
		const div = document.createElement('div');

		div.textContent = text;

		return div.innerHTML;
	}

	/**
	 * A Google session {session, expiry (ms)} for a layer, shared by all maps on the page.
	 */
	function googleSession(def, renew = false) {
		const key = def.tiles[0].key;
		const cache_key = SESSION_KEY + def.id;
		const cached = sessions[def.id];

		if (cached && !renew) {
			return cached;
		}

		if (!renew) {
			try {
				const saved = JSON.parse(storageGet(cache_key) || 'null');

				if (saved && saved.key === key && saved.expiry - Date.now() > DAY) {
					return sessions[def.id] = Promise.resolve(saved);
				}
			}
			catch (e) {}
		}

		const promise = fetch(GOOGLE_API + 'createSession?key=' + encodeURIComponent(key), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(def.google)
		})
			.then(r => r.json().then(data => r.ok && data.session
				? data
				: Promise.reject(new Error((data.error && data.error.message) || 'HTTP ' + r.status))
			))
			.then(data => {
				const saved = {key, session: data.session, expiry: parseInt(data.expiry, 10) * 1000};

				storageSet(cache_key, JSON.stringify(saved));

				return saved;
			});

		sessions[def.id] = promise;
		promise.catch(() => {
			if (sessions[def.id] === promise) {
				delete sessions[def.id];
			}
		});

		return promise;
	}

	function makeLayer(def) {
		const build = (extra = {}) => {
			const tiles = def.tiles.map(({url, ...options}) => L.tileLayer(url, {
				minZoom: 0,
				maxZoom: Math.max(MAX_ZOOM, options.maxNativeZoom || 0),
				...options,
				...extra
			}));

			return tiles.length == 1 ? tiles[0] : L.layerGroup(tiles);
		};

		if (!def.google) {
			return Promise.resolve(build());
		}

		return googleSession(def).then(saved => {
			const layer = build({session: saved.session});

			layer._geomapGoogle = {def, expiry: saved.expiry};

			return layer;
		});
	}

	/**
	 * Keeps a Google layer's session fresh on pages left open (wall screens) and its copyright line current.
	 */
	function startGoogle(map, layer) {
		const google = layer._geomapGoogle;
		const def = google.def;
		let attribution = layer.options.attribution;
		let timer = null;
		let renew_timer = null;

		const setAttribution = text => {
			if (text !== attribution) {
				map.attributionControl.removeAttribution(attribution);
				attribution = text;
				layer.options.attribution = text;
				map.attributionControl.addAttribution(text);
			}
		};

		const update = () => {
			const b = map.getBounds();
			const clampLat = v => Math.max(-89.9, Math.min(89.9, v));
			const wrapLng = v => Math.max(-179.99, Math.min(179.99, v));
			const whole = b.getEast() - b.getWest() >= 360;
			const query = new URLSearchParams({
				session: layer.options.session,
				key: def.tiles[0].key,
				zoom: Math.round(map.getZoom()),
				north: clampLat(b.getNorth()),
				south: clampLat(b.getSouth()),
				east: whole ? 179.99 : wrapLng(L.Util.wrapNum(b.getEast(), [-180, 180], true)),
				west: whole ? -179.99 : wrapLng(L.Util.wrapNum(b.getWest(), [-180, 180], true))
			});

			fetch(GOOGLE_API.replace('/v1/', '/tile/v1/') + 'viewport?' + query)
				.then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)))
				.then(data => {
					if (data.copyright) {
						setAttribution(escapeHtml(data.copyright));
					}
				})
				.catch(e => console.warn('Geomap layers: Google copyright not loaded.', e));
		};

		const onMove = () => {
			clearTimeout(timer);
			timer = setTimeout(update, 400);
		};

		const scheduleRenew = () => {
			const wait = Math.max(60000, Math.min(google.expiry - Date.now() - DAY, 2147483647));

			renew_timer = setTimeout(() => {
				googleSession(def, true)
					.then(saved => {
						google.expiry = saved.expiry;
						layer.options.session = saved.session;
						layer.redraw();
					})
					.catch(e => console.warn('Geomap layers: Google session not renewed.', e))
					.finally(scheduleRenew);
			}, wait);
		};

		map.on('moveend', onMove);
		update();
		scheduleRenew();

		const logo = new L.Control.GeomapGoogleLogo({src: def.logo}).addTo(map);

		return () => {
			map.off('moveend', onMove);
			clearTimeout(timer);
			clearTimeout(renew_timer);
			logo.remove();
		};
	}

	L.Control.GeomapGoogleLogo = L.Control.extend({
		options: {position: 'bottomleft', src: ''},

		onAdd: function() {
			const div = L.DomUtil.create('div', 'leaflet-control geomap-layers-google-logo');

			// Without Google's logo file, its name (Google allows that where the logo does not fit).
			if (this.options.src) {
				const img = L.DomUtil.create('img', '', div);

				img.src = this.options.src;
				img.alt = 'Google Maps';
				img.setAttribute('aria-label', 'Google Maps');
			}
			else {
				L.DomUtil.create('span', 'geomap-layers-google-text', div).textContent = 'Google Maps';
			}

			return div;
		}
	});

	L.Control.GeomapLayers = L.Control.extend({
		includes: L.Evented.prototype,

		options: {position: 'topright'},

		initialize: function(entries, current, options) {
			L.setOptions(this, options);
			this._entries = entries;
			this._current = current;
		},

		onAdd: function(map) {
			const div = L.DomUtil.create('div', 'leaflet-bar leaflet-control geomap-layers');
			const btn = L.DomUtil.create('a', 'geomap-layers-button', div);

			btn.ariaLabel = t('Map layers');
			btn.title = t('Map layers');
			btn.role = 'button';
			btn.href = '#';

			const bar = L.DomUtil.create('ul', 'checkbox-list geomap-filter geomap-layers-list', div);
			const name = 'geomaplayers_' + L.Util.stamp(this);
			let group = null;

			for (const entry of this._entries) {
				if (entry.group !== group) {
					group = entry.group;
					L.DomUtil.create('li', 'geomap-layers-group', bar).textContent = group;
				}

				const li = L.DomUtil.create('li', '', bar);
				const radio = L.DomUtil.create('input', 'checkbox-radio', li);
				const label = L.DomUtil.create('label', '', li);

				radio.type = 'radio';
				radio.name = name;
				radio.value = entry.id;
				radio.id = name + '_' + entry.id;
				radio.checked = entry.id === this._current;
				label.htmlFor = radio.id;
				label.append(L.DomUtil.create('span', ''), document.createTextNode(entry.name));
			}

			L.DomEvent.disableClickPropagation(div);
			L.DomEvent.disableScrollPropagation(div);
			L.DomEvent.on(btn, 'click', e => {
				L.DomEvent.preventDefault(e);

				// Fit the list inside the widget; it scrolls when the widget is small.
				const room = map.getContainer().clientHeight - div.offsetTop - btn.offsetHeight - 45;

				bar.style.maxHeight = Math.max(80, room) + 'px';
				bar.classList.toggle('collapsed');
			});
			L.DomEvent.on(bar, 'change', e => {
				if (e.target.checked) {
					this.fire('pick', {id: e.target.value});
				}
			});

			this._bar = bar;
			this._closer = e => {
				if (!div.contains(e.target)) {
					this.close();
				}
			};
			map.getContainer().addEventListener('click', this._closer);

			return div;
		},

		onRemove: function(map) {
			map.getContainer().removeEventListener('click', this._closer);
		},

		setCurrent: function(id) {
			this._current = id;

			if (this._bar) {
				for (const radio of this._bar.querySelectorAll('input[type="radio"]')) {
					radio.checked = radio.value === id;
				}
			}
		},

		close: function() {
			this._bar && this._bar.classList.remove('collapsed');
		}
	});

	function attach(map, default_layer) {
		loadList().then(({button, layers}) => {
			// The widget may have been removed (dashboard page change) while the list was loading.
			if (!map._container || !map._container.isConnected) {
				return;
			}

			// The Zabbix default map, named after the matching layer if it is one of them.
			const marker = default_layer._geomapMarker || null;
			const same = marker !== null
				? layers.find(def => def.marker === marker)
				: layers.find(def => def.tiles.length == 1 && sameUrl(def.tiles[0].url, default_layer._url));
			const entries = [{
				id: DEFAULT_ID,
				name: same ? same.name + ' ' + t('(default)') : t('Default map'),
				group: same ? same.group : t('Default'),
				layer: default_layer,
				def: same || null
			}];

			for (const def of layers) {
				if (def !== same && !def.hidden && Array.isArray(def.tiles) && def.tiles.length > 0) {
					entries.push({id: def.id, name: def.name, group: def.group || '', layer: null, def});
				}
			}

			// Keep each group together, in server order, with the default's group first.
			const groups = [...new Set(entries.map(e => e.group))];
			entries.sort((a, b) => groups.indexOf(a.group) - groups.indexOf(b.group));

			const state = {entries, current: entries[0], wanted: null, stop: null};
			const control = new L.Control.GeomapLayers(entries, DEFAULT_ID);

			map._geomapLayers = {state, control};
			maps.add(map);
			map.on('unload', () => {
				maps.delete(map);
				state.stop && state.stop();
			});

			if (marker !== null) {
				startDefaultGoogle(map, entries[0]);
			}

			if (!button || entries.length < 2) {
				return;
			}

			control.addTo(map);
			control.on('pick', e => {
				storageSet(STORAGE_KEY, e.id === DEFAULT_ID ? null : e.id);

				for (const m of maps) {
					show(m, e.id);
				}
			});

			show(map, storageGet(STORAGE_KEY) || DEFAULT_ID);
		});
	}

	/**
	 * Google as the Zabbix default map: give the blank default layer a session and the real Google URL.
	 */
	function startDefaultGoogle(map, entry) {
		const layer = entry.layer;
		const def = entry.def;

		if (!def) {
			console.warn('Geomap layers: the default map is Google, but no Google Maps API key is set.');

			return;
		}

		googleSession(def)
			.then(saved => {
				L.setOptions(layer, {session: saved.session, key: def.tiles[0].key});
				layer._geomapGoogle = {def, expiry: saved.expiry};
				layer.setUrl(def.tiles[0].url);

				const {state} = map._geomapLayers;

				if (state.current === entry && maps.has(map)) {
					state.stop && state.stop();
					state.stop = startGoogle(map, layer);
				}
			})
			.catch(e => console.warn('Geomap layers: could not open ' + def.name + '.', e));
	}

	function show(map, id) {
		const {state, control} = map._geomapLayers;
		const entry = state.entries.find(e => e.id === id) || state.entries[0];

		state.wanted = entry;
		control.setCurrent(entry.id);
		control.close();

		if (entry === state.current) {
			return;
		}

		const ready = entry.layer !== null ? Promise.resolve(entry.layer) : makeLayer(entry.def);

		ready
			.then(layer => {
				entry.layer = layer;

				// Another pick came in while this one was loading, or the widget is gone.
				if (state.wanted !== entry || !maps.has(map)) {
					return;
				}

				state.stop && state.stop();
				state.stop = null;
				map.removeLayer(state.current.layer);
				layer.addTo(map);

				if (layer.bringToBack) {
					layer.bringToBack();
				}

				if (layer._geomapGoogle) {
					state.stop = startGoogle(map, layer);
				}

				state.current = entry;
			})
			.catch(e => {
				console.warn('Geomap layers: could not open ' + entry.name + '.', e);

				if (state.wanted === entry) {
					state.wanted = state.current;
					control.setCurrent(state.current.id);
				}
			});
	}

	// A Google default map: load nothing until startDefaultGoogle() has a session.
	L.TileLayer.addInitHook(function() {
		if (typeof this._url === 'string' && this._url.indexOf(GOOGLE_MARKER) === 0) {
			this._geomapMarker = this._url;
			this._url = BLANK_TILE;
		}
	});

	/*
	 * Zabbix fix: "Reset to initial view" on a geomap without an "Initial view" setting cleared the saved view
	 * but left the map where it was until the page was reloaded. Now it moves to a view that fits the hosts (or the server's view if there are none).
	 */
	let reset_patched = false;

	// The first default view a map gets is the one the server sends, i.e. what a page reload shows.
	const setDefaultView = L.Map.prototype.setDefaultView;

	L.Map.include({
		setDefaultView: function(latLng, zoom) {
			if (!this._geomapFirstView) {
				this._geomapFirstView = {latLng, zoom};
			}

			return setDefaultView.call(this, latLng, zoom);
		}
	});

	function patchResetView() {
		if (reset_patched || typeof CWidgetGeoMap === 'undefined' || !CWidgetGeoMap.prototype.unsetDefaultView) {
			return;
		}

		const unsetDefaultView = CWidgetGeoMap.prototype.unsetDefaultView;

		CWidgetGeoMap.prototype.unsetDefaultView = function(...args) {
			const had_initial = 'initial' in this._home_coords;
			const result = unsetDefaultView.apply(this, args);

			if (!had_initial && this._map !== null) {
				const map = this._map;
				const bounds = this._markers ? this._markers.getBounds() : null;

				if (bounds && bounds.isValid()) {
					// Fit the hosts, but not closer than a city view if there is only one.
					map.fitBounds(bounds, {maxZoom: 12, padding: [30, 30]});
				}
				else {
					const first = map._geomapFirstView || {latLng: L.latLng(0, 0), zoom: 1};

					map.setView(first.latLng, first.zoom);
				}

				map.setDefaultView(map.getCenter(), map.getZoom());
			}

			return result;
		};

		reset_patched = true;
	}

	L.Map.addInitHook(function() {
		const map = this;

		patchResetView();
		const onLayer = e => {
			if (e.layer instanceof L.TileLayer) {
				map.off('layeradd', onLayer);
				attach(map, e.layer);
			}
		};

		map.on('layeradd', onLayer);
	});
})();
