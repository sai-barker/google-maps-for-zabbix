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
use Modules\GeomapLayers\Lib\Layers;
use Modules\GeomapLayers\Lib\Settings;

/**
 * GET -> JSON {button, layers: [{id, name, group, hidden, tiles: [{url, ...Leaflet options}], google?, logo?, marker?}]}
 * for the geomap widgets. Read-only; the same for every user.
 */
class LayerList extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		return true;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function doAction(): void {
		$this->setResponse(new CControllerResponseData(['main_block' => json_encode([
			'button' => (bool) Settings::get()['layers_button'],
			'layers' => Layers::forBrowser()
		])]));
	}
}
