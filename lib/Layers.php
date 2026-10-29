<?php declare(strict_types = 0);
/*
** Google Maps for Zabbix (Geomap layers module).
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**/


namespace Modules\GeomapLayers\Lib;

/**
 * The background maps the module knows: Google (when an API key is set) and free tile servers.
 */
class Layers {

	private const OSM = '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors';
	private const ESRI = 'Tiles &copy; <a href="https://www.esri.com" target="_blank">Esri</a>';
	private const ESRI_URL = 'https://server.arcgisonline.com/ArcGIS/rest/services/';

	/**
	 * All layers with 'hidden' set from the settings; what the browser gets.
	 */
	public static function forBrowser(): array {
		$settings = Settings::get();
		$hidden = array_flip($settings['hidden_layers']);
		$layers = [];

		foreach (self::all($settings) as $layer) {
			$layers[] = $layer + ['hidden' => isset($hidden[$layer['id']])];
		}

		return $layers;
	}

	/**
	 * id => name of all layers, for the settings page.
	 */
	public static function names(array $settings): array {
		return array_column(self::all($settings, true), 'name', 'id');
	}

	public static function all(array $settings, bool $with_google = false): array {
		return array_merge(self::google($settings, $with_google), self::free());
	}

	private static function google(array $settings, bool $always): array {
		$key = trim($settings['google_key']);

		if ($key === '' && !$always) {
			return [];
		}

		$session = ['language' => $settings['google_language'] !== '' ? $settings['google_language'] : 'en'];

		if ($settings['google_region'] !== '') {
			$session['region'] = $settings['google_region'];
		}

		$logo = Settings::googleLogo();
		$layers = [];

		foreach (Settings::googleTypes() as $id => $type) {
			$layers[] = [
				'id' => $id,
				'name' => $type['name'],
				'group' => 'Google',
				'google' => $type['session'] + $session,
				'logo' => $logo,
				'marker' => Settings::googleMarker($id),
				'tiles' => [[
					'url' => Settings::GOOGLE_TILES,
					'key' => $key,
					'maxNativeZoom' => $type['max_zoom'],
					'attribution' => 'Google'
				]]
			];
		}

		return $layers;
	}

	private static function free(): array {
		$esri_imagery = [
			'url' => self::ESRI_URL.'World_Imagery/MapServer/tile/{z}/{y}/{x}',
			'maxNativeZoom' => 19,
			'attribution' => self::ESRI.' &mdash; Source: Esri, Maxar, Earthstar Geographics, and the GIS User Community'
		];

		return [
			[
				'id' => 'osm',
				'name' => 'OpenStreetMap',
				'group' => _('Street'),
				'tiles' => [[
					'url' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
					'maxNativeZoom' => 19,
					'attribution' => self::OSM
				]]
			],
			[
				'id' => 'esri-streets',
				'name' => _('Esri streets'),
				'group' => _('Street'),
				'tiles' => [[
					'url' => self::ESRI_URL.'World_Street_Map/MapServer/tile/{z}/{y}/{x}',
					'maxNativeZoom' => 19,
					'attribution' => self::ESRI.' &mdash; Source: Esri, HERE, Garmin, USGS, FAO, NOAA, OpenStreetMap contributors'
				]]
			],
			[
				'id' => 'opentopomap',
				'name' => 'OpenTopoMap',
				'group' => _('Terrain'),
				'tiles' => [[
					'url' => 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
					'subdomains' => 'abc',
					'maxNativeZoom' => 17,
					'attribution' => 'Map data: '.self::OSM.', SRTM | Map style: &copy; <a href="https://opentopomap.org" target="_blank">OpenTopoMap</a> (CC-BY-SA)'
				]]
			],
			[
				'id' => 'esri-satellite',
				'name' => _('Esri satellite'),
				'group' => _('Satellite'),
				'tiles' => [$esri_imagery]
			],
			[
				'id' => 'esri-hybrid',
				'name' => _('Esri hybrid'),
				'group' => _('Satellite'),
				'tiles' => [
					$esri_imagery,
					['url' => self::ESRI_URL.'Reference/World_Transportation/MapServer/tile/{z}/{y}/{x}', 'maxNativeZoom' => 19],
					['url' => self::ESRI_URL.'Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', 'maxNativeZoom' => 19]
				]
			]
		];
	}
}
