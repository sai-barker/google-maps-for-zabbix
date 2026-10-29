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

use APP;

/**
 * Module settings, kept in the module's own config in the Zabbix database (Administration > General >
 * Geographical maps saves them), and the Google map types.
 *
 * Zabbix only stores a built-in tile provider or "Other", so a Google default map is saved as "Other" with a
 * marker URL (GOOGLE_MARKER) that the module recognises on the settings page and on dashboards.
 */
class Settings {

	public const MODULE_ID = 'geomaplayers';
	public const GOOGLE_MARKER = 'https://tile.googleapis.com/v1/2dtiles/{z}/{x}/{y}?geomaplayers=';
	public const GOOGLE_TILES = 'https://tile.googleapis.com/v1/2dtiles/{z}/{x}/{y}?session={session}&key={key}';

	public const DEFAULTS = [
		'google_key' => '',
		'google_language' => 'en',
		'google_region' => '',
		'layers_button' => 1,
		'hidden_layers' => []
	];

	/**
	 * Google map types: provider id => name, Map Tiles API session request, max zoom.
	 */
	public static function googleTypes(): array {
		return [
			'google-roadmap' => [
				'name' => _('Google road map'),
				'session' => ['mapType' => 'roadmap'],
				'max_zoom' => 22
			],
			'google-satellite' => [
				'name' => _('Google satellite'),
				'session' => ['mapType' => 'satellite'],
				'max_zoom' => 21
			],
			'google-hybrid' => [
				'name' => _('Google hybrid'),
				'session' => ['mapType' => 'satellite', 'layerTypes' => ['layerRoadmap']],
				'max_zoom' => 21
			],
			'google-terrain' => [
				'name' => _('Google terrain'),
				'session' => ['mapType' => 'terrain', 'layerTypes' => ['layerRoadmap']],
				'max_zoom' => 15
			]
		];
	}

	public static function get(): array {
		$module = APP::ModuleManager()->getModule(self::MODULE_ID);
		$config = $module !== null ? $module->getConfig() : [];

		return array_intersect_key($config, self::DEFAULTS) + self::DEFAULTS;
	}

	public static function save(array $config): void {
		$module = APP::ModuleManager()->getModule(self::MODULE_ID);

		if ($module !== null) {
			$module->setConfig(array_intersect_key($config, self::DEFAULTS) + self::get());
		}
	}

	/**
	 * Google provider id for a stored tile URL, or null when it is not a Google marker.
	 */
	public static function googleFromUrl(string $url): ?string {
		if (strpos($url, self::GOOGLE_MARKER) !== 0) {
			return null;
		}

		$id = 'google-'.substr($url, strlen(self::GOOGLE_MARKER));

		return array_key_exists($id, self::googleTypes()) ? $id : null;
	}

	public static function googleMarker(string $id): string {
		return self::GOOGLE_MARKER.substr($id, strlen('google-'));
	}

	/**
	 * Google's logo file, which Google requires on the map while its tiles are shown. Not shipped with the module:
	 * the README says where to get it.
	 */
	public static function googleLogo(): ?string {
		$module = APP::ModuleManager()->getModule(self::MODULE_ID);

		if ($module === null) {
			return null;
		}

		foreach (['svg', 'png'] as $ext) {
			$file = 'assets/img/google-maps-logo.'.$ext;

			if (is_file(dirname(__DIR__).'/'.$file)) {
				return $module->getRelativePath().'/'.$file;
			}
		}

		return null;
	}
}
