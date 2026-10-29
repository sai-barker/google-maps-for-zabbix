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

use API;
use CController;
use CControllerResponseFatal;
use CControllerResponseRedirect;
use CMessageHelper;
use CRoleHelper;
use CSettingsHelper;
use CUrl;
use CWebUser;
use Modules\GeomapLayers\Lib\Layers;
use Modules\GeomapLayers\Lib\Settings;

/**
 * Saves Administration > General > Geographical maps: the Zabbix settings (a Google map is stored as "Other" with
 * the module's marker URL) and, for Super admins, the module settings (Google key, Layers button).
 */
class GeomapsUpdate extends CController {

	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'geomaps_tile_provider' =>	'required|string',
			'geomaps_tile_url' =>		'string',
			'geomaps_max_zoom' =>		'string',
			'geomaps_attribution' =>	'string',
			'google_key' =>				'string',
			'google_language' =>		'string',
			'google_region' =>			'string',
			'layers_button' =>			'in 0,1',
			'shown_layers' =>			'array'
		]);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	private function fail(string $error): void {
		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))->setArgument('action', 'geomaps.edit'));
		$response->setFormData($this->getInputAll());
		CMessageHelper::setErrorTitle(_('Cannot update configuration'));

		if ($error !== '') {
			CMessageHelper::addError($error);
		}

		$this->setResponse($response);
	}

	protected function doAction(): void {
		$can_edit_module = CWebUser::getType() == USER_TYPE_SUPER_ADMIN;
		$module = Settings::get();

		if ($can_edit_module) {
			$module['google_key'] = trim($this->getInput('google_key', ''));
			$module['google_language'] = trim($this->getInput('google_language', ''));
			$module['google_region'] = strtoupper(trim($this->getInput('google_region', '')));
			$module['layers_button'] = (int) $this->getInput('layers_button', 0);

			if ($module['google_key'] !== '' && !preg_match('/^[A-Za-z0-9_-]{20,100}$/', $module['google_key'])) {
				$this->fail(_('Google Maps API key: only letters, digits, "-" and "_" are allowed.'));

				return;
			}

			if ($module['google_language'] !== '' && !preg_match('/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*$/', $module['google_language'])) {
				$this->fail(_('Google map language must be a language code such as "en" or "en-AU".'));

				return;
			}

			if ($module['google_region'] !== '' && !preg_match('/^[A-Z]{2}$/', $module['google_region'])) {
				$this->fail(_('Google map region must be a two-letter country code such as "AU".'));

				return;
			}

			$shown = array_flip($this->getInput('shown_layers', []));
			$module['hidden_layers'] = array_values(array_filter(array_keys(Layers::names($module)),
				static fn(string $id) => !isset($shown[$id])
			));
		}

		$provider = $this->getInput('geomaps_tile_provider');
		$google_types = Settings::googleTypes();

		if (array_key_exists($provider, $google_types)) {
			if ($module['google_key'] === '') {
				$this->fail(_('Enter a Google Maps API key to use a Google map.'));

				return;
			}

			$settings = [
				CSettingsHelper::GEOMAPS_TILE_PROVIDER => '',
				CSettingsHelper::GEOMAPS_TILE_URL => Settings::googleMarker($provider),
				CSettingsHelper::GEOMAPS_MAX_ZOOM => (string) $google_types[$provider]['max_zoom'],
				CSettingsHelper::GEOMAPS_ATTRIBUTION => 'Google'
			];
		}
		else {
			$tile_url = trim($this->getInput('geomaps_tile_url', ''));
			$max_zoom = $this->getInput('geomaps_max_zoom', '');

			if ($provider === '' && $tile_url === '') {
				$this->fail(_s('Incorrect value for field "%1$s": %2$s.', _('Tile URL'), _('cannot be empty')));

				return;
			}

			if (!ctype_digit($max_zoom) || $max_zoom < 1 || $max_zoom > ZBX_GEOMAP_MAX_ZOOM) {
				$this->fail(_s('Incorrect value for field "%1$s": %2$s.', _('Max zoom level'),
					_s('value must be no less than "%1$s" and no greater than "%2$s"', 1, ZBX_GEOMAP_MAX_ZOOM)
				));

				return;
			}

			$settings = [
				CSettingsHelper::GEOMAPS_TILE_PROVIDER => $provider,
				CSettingsHelper::GEOMAPS_TILE_URL => $tile_url,
				CSettingsHelper::GEOMAPS_MAX_ZOOM => $max_zoom,
				CSettingsHelper::GEOMAPS_ATTRIBUTION => trim($this->getInput('geomaps_attribution', ''))
			];
		}

		if (!API::Settings()->update($settings)) {
			$this->fail('');

			return;
		}

		if ($can_edit_module) {
			Settings::save($module);
		}

		CMessageHelper::setSuccessTitle(_('Configuration updated'));
		$this->setResponse(new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))->setArgument('action', 'geomaps.edit')
		));
	}
}
