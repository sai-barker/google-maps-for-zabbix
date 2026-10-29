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


namespace Modules\GeomapLayers\Actions;

use CController;
use CControllerResponseData;
use CRoleHelper;
use CSettingsHelper;
use CWebUser;
use Modules\GeomapLayers\Lib\Layers;
use Modules\GeomapLayers\Lib\Settings;

/**
 * Administration > General > Geographical maps, with Google map types, the Google API key and the Layers button
 * settings. Replaces the built-in geomaps.edit action while the module is enabled.
 */
class GeomapsEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		// Only filled after a failed save (form data of the redirect).
		return $this->validateInput([
			'geomaps_tile_provider' => 'string',
			'geomaps_tile_url' => 'string',
			'geomaps_max_zoom' => 'string',
			'geomaps_attribution' => 'string'
		]);
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction(): void {
		$tile_providers = getTileProviders();
		$google_types = Settings::googleTypes();
		$settings = Settings::get();

		$provider = CSettingsHelper::get(CSettingsHelper::GEOMAPS_TILE_PROVIDER);
		$tile_url = CSettingsHelper::get(CSettingsHelper::GEOMAPS_TILE_URL);

		$data = [
			'tile_providers' => $tile_providers,
			'google_types' => array_map(static fn(array $type) => [
				'name' => $type['name'],
				'geomaps_max_zoom' => (string) $type['max_zoom']
			], $google_types),
			'settings' => $settings,
			'layers' => Layers::names($settings),
			'google_logo' => Settings::googleLogo() !== null,
			'can_edit_module' => CWebUser::getType() == USER_TYPE_SUPER_ADMIN
		];

		$google = $provider === '' ? Settings::googleFromUrl($tile_url) : null;

		if ($google !== null) {
			$data += [
				'geomaps_tile_provider' => $google,
				'geomaps_tile_url' => '',
				'geomaps_max_zoom' => (string) $google_types[$google]['max_zoom'],
				'geomaps_attribution' => ''
			];
		}
		elseif ($provider === '') {
			$data += [
				'geomaps_tile_provider' => '',
				'geomaps_tile_url' => $tile_url,
				'geomaps_max_zoom' => CSettingsHelper::get(CSettingsHelper::GEOMAPS_MAX_ZOOM),
				'geomaps_attribution' => CSettingsHelper::get(CSettingsHelper::GEOMAPS_ATTRIBUTION)
			];
		}
		elseif (array_key_exists($provider, $tile_providers)) {
			$data += ['geomaps_tile_provider' => $provider] + $tile_providers[$provider];
		}
		else {
			$data += ['geomaps_tile_provider' => array_key_first($tile_providers)] + reset($tile_providers);
		}

		// Values typed before a failed save.
		foreach (['geomaps_tile_provider', 'geomaps_tile_url', 'geomaps_max_zoom', 'geomaps_attribution'] as $field) {
			if ($this->hasInput($field)) {
				$data[$field] = $this->getInput($field);
			}
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Geographical maps'));
		$this->setResponse($response);
	}
}
