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
 * Administration > General > Geographical maps, as in Zabbix 7.2, plus Google map types, the Google Maps API key and
 * the Layers button settings (Geomap layers module).
 *
 * @var CView $this
 * @var array $data
 */

$this->includeJsFile('geomaplayers.geomaps.edit.js.php');

$hintbox_tile_url = makeHelpIcon([
	_('The URL template is used to load and display the tile layer on geographical maps.'),
	BR(),
	BR(),
	_('Example'), ': ', (new CSpan('https://{s}.example.com/{z}/{x}/{y}{r}.png'))->addClass(ZBX_STYLE_MONOSPACE_FONT),
	BR(),
	BR(),
	_('The following placeholders are supported:'),
	(new CList([
		new CListItem([
			(new CSpan('{s}'))->addClass(ZBX_STYLE_MONOSPACE_FONT), ' ',
			_('represents one of the available subdomains;')
		]),
		new CListItem([
			(new CSpan('{z}'))->addClass(ZBX_STYLE_MONOSPACE_FONT), ' ',
			_('represents zoom level parameter in the URL;')
		]),
		new CListItem([
			(new CSpan('{x}'))->addClass(ZBX_STYLE_MONOSPACE_FONT), ' ', _('and'), ' ',
			(new CSpan('{y}'))->addClass(ZBX_STYLE_MONOSPACE_FONT), ' ', _('represent tile coordinates;')
		]),
		new CListItem([
			(new CSpan('{r}'))->addClass(ZBX_STYLE_MONOSPACE_FONT), ' ',
			_('can be used to add "@2x" to the URL to load retina tiles.')
		])
	]))->addClass(ZBX_STYLE_LIST_DASHED)
]);

$warning_attribution = makeWarningIcon(_('Tile provider attribution data displayed in a small text box on the map.'));

$hintbox_max_zoom = makeHelpIcon(_('Maximum zoom level of the map.'));

$is_google = array_key_exists($data['geomaps_tile_provider'], $data['google_types']);

$default_fields = (new CFormFieldset(_('Default map')))
	->addItem([
		(new CLabel(_('Tile provider'), 'label-provider'))->setAsteriskMark(),
		new CFormField(
			(new CSelect('geomaps_tile_provider'))
				->setValue($data['geomaps_tile_provider'])
				->setFocusableElementId('label-provider')
				->addOptionGroup((new CSelectOptionGroup('Google'))->addOptions(CSelect::createOptionsFromArray(
					array_combine(array_keys($data['google_types']), array_column($data['google_types'], 'name'))
				)))
				->addOptionGroup((new CSelectOptionGroup(_('Other providers')))->addOptions(CSelect::createOptionsFromArray(
					array_combine(array_keys($data['tile_providers']), array_column($data['tile_providers'], 'name')) +
					['' => _('Other')]
				)))
		)
	])
	->addItem([
		(new CLabel([_('Tile URL'), $hintbox_tile_url], 'geomaps_tile_url'))
			->setAsteriskMark()
			->addClass($is_google ? ZBX_STYLE_DISPLAY_NONE : null),
		(new CFormField(
			(new CTextBox('geomaps_tile_url', $data['geomaps_tile_url'], false,
				DB::getFieldLength('config', 'geomaps_tile_url'))
			)
				->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
				->setReadonly($data['geomaps_tile_provider'] !== '')
				->setAriaRequired()
		))->addClass($is_google ? ZBX_STYLE_DISPLAY_NONE : null)
	])
	->addItem([
		(new CLabel([_('Attribution text'), $warning_attribution], 'geomaps_attribution'))
			->addClass($data['geomaps_tile_provider'] !== '' ? ZBX_STYLE_DISPLAY_NONE : null),
		(new CFormField(
			(new CTextArea('geomaps_attribution', $data['geomaps_attribution']))
				->addClass(ZBX_STYLE_MONOSPACE_FONT)
				->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
				->setMaxLength(DB::getFieldLength('config', 'geomaps_attribution'))
		))->addClass($data['geomaps_tile_provider'] !== '' ? ZBX_STYLE_DISPLAY_NONE : null)
	])
	->addItem([
		(new CLabel([_('Max zoom level'), $hintbox_max_zoom], 'geomaps_max_zoom'))->setAsteriskMark(),
		new CFormField(
			(new CNumericBox('geomaps_max_zoom', $data['geomaps_max_zoom'], 2, false, false, false))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setReadonly($data['geomaps_tile_provider'] !== '')
				->setAriaRequired()
		)
	]);

// Google Maps and the Layers button: module settings, Super admins only (they are the module's config).
$readonly = !$data['can_edit_module'];

$google_help = makeHelpIcon([
	_('A Google Maps Platform API key with the Map Tiles API enabled.'), BR(),
	_('Restrict the key to this website (HTTP referrer) and to the Map Tiles API: every user\'s browser gets it.')
]);

$google_fields = (new CFormFieldset(_('Google Maps')))
	->addItem([
		new CLabel([_('API key'), $google_help], 'google_key'),
		new CFormField([
			(new CTextBox('google_key', $data['settings']['google_key'], $readonly, 100))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('autocomplete', 'off')
				->setAttribute('spellcheck', 'false'),
			(new CDiv([
				(new CSimpleButton(_('Test key')))
					->setId('google_key_test')
					->addClass(ZBX_STYLE_BTN_GREY)
					->setEnabled(!$readonly),
				(new CSpan())->setId('google_key_result')
			]))->addClass('geomaplayers-test')
		])
	])
	->addItem([
		new CLabel([_('Language'), makeHelpIcon(_('Language of map labels, for example "en" or "en-AU".'))],
			'google_language'
		),
		new CFormField(
			(new CTextBox('google_language', $data['settings']['google_language'], $readonly, 20))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		)
	])
	->addItem([
		new CLabel([_('Region'),
			makeHelpIcon(_('Two-letter country code for borders and place names, for example "AU". Optional.'))
		], 'google_region'),
		new CFormField(
			(new CTextBox('google_region', $data['settings']['google_region'], $readonly, 2))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
		)
	]);

if (!$data['google_logo']) {
	$google_fields->addItem([
		new CLabel(_('Google logo')),
		new CFormField(
			(new CDiv(_('Google requires its logo on the map. Add google-maps-logo.svg to the module\'s assets/img folder (see the module README).')))
				->addClass(ZBX_STYLE_RED)
		)
	]);
}

$layers_list = (new CCheckBoxList('shown_layers'))
	->setVertical()
	->setReadonly($readonly)
	->setOptions(array_map(
		static fn(string $id, string $name) => [
			'label' => $name,
			'value' => $id,
			'checked' => !in_array($id, $data['settings']['hidden_layers'])
		],
		array_keys($data['layers']), $data['layers']
	));

$layers_fields = (new CFormFieldset(_('Layers button')))
	->addItem([
		new CLabel(_('Show Layers button'), 'layers_button'),
		new CFormField(
			(new CCheckBox('layers_button'))
				->setChecked((bool) $data['settings']['layers_button'])
				->setReadonly($readonly)
				->setUncheckedValue('0')
		)
	])
	->addItem([
		new CLabel([_('Maps in the Layers button'),
			makeHelpIcon(_('Each user picks a map with the Layers button on a Geomap widget; their browser remembers the pick. Google maps need the API key.'))
		]),
		new CFormField($layers_list)
	]);

$form_grid = (new CFormGrid())
	->addItem($default_fields)
	->addItem($google_fields)
	->addItem($layers_fields);

if ($readonly) {
	$form_grid->addItem(new CFormField(
		(new CDiv(_('Only Super admins can change the Google Maps and Layers button settings.')))->addClass(ZBX_STYLE_GREY)
	));
}

$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('geomaps.update')))->removeId())
	->setId('geomaps-form')
	->setName('geomaps-form')
	->setAction(
		(new CUrl('zabbix.php'))
			->setArgument('action', 'geomaps.update')
			->getUrl()
	)
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->addItem(
		(new CTabView())
			->addTab('geomaps_tab', _('Geographical maps'), $form_grid)
			->setFooter(makeFormFooter(
				new CSubmit('update', _('Update'))
			))
	);

(new CHtmlPage())
	->setTitle(_('Geographical maps'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu())
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_GEOMAPS_EDIT))
	->addItem($form)
	->show();

(new CScriptTag(
	'view.init('.json_encode([
		'tile_providers' => $data['tile_providers'],
		'google_types' => $data['google_types']
	]).');'
))
	->setOnDocumentReady()
	->show();
