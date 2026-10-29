# Google Maps for Zabbix

A Zabbix 7.2 frontend module that brings Google Maps to the **Geomap** dashboard widget, set up entirely from the
Zabbix frontend.

- **Google as the default map.** Administration > General > Geographical maps gets Google road map, satellite,
  hybrid and terrain in the *Tile provider* list, next to the built-in providers.
- **Google Maps API key in the frontend.** Enter the key on the same page, test it with one click, and set the map
  language and region. It is stored in the module's configuration in the Zabbix database. You don't edit any files.
- **Layers button.** Every Geomap widget gets a Layers button, so each user can switch between Google maps and free
  maps (OpenStreetMap, OpenTopoMap, Esri streets, satellite and hybrid). Their browser remembers the pick. You choose
  which maps the button offers, or turn the button off.
- **Done the way Google allows.** Tiles come from Google's official
  [Map Tiles API](https://developers.google.com/maps/documentation/tile). The module shows Google's logo and the
  copyright line Google returns for the area in view, and renews Google sessions on wall screens left open for weeks.
- **Fix included.** On a Geomap without an *Initial view* setting, "Reset to initial view" now moves the map
  immediately instead of after a page reload.

No Zabbix core files are changed. Disabling the module brings back the stock settings page.

## Requirements

- Zabbix 7.2 (tested on 7.2.15).
- For the Google maps: a Google Cloud project with billing enabled and the **Map Tiles API** turned on. The free maps
  work without it.

## Install

1. Copy this folder to the Zabbix frontend's `modules` directory, for example:

   ```
   cd /usr/share/zabbix/modules        # Zabbix 7.2 packages: /usr/share/zabbix/ui/modules
   git clone https://github.com/sai-barker/google-maps-for-zabbix.git
   ```

2. Add Google's logo. Google requires its logo on the map, but this repository doesn't include it. Download
   [Google_Maps_Attribution_Assets.zip](https://developers.google.com/static/maps/documentation/images/Google_Maps_Attribution_Assets.zip)
   (linked from Google's [Map Tiles API policies](https://developers.google.com/maps/documentation/tile/policies)),
   and copy `GoogleMaps_Logo_WithLightOutline/GoogleMaps_Logo_WithLightOutline.svg` to
   `assets/img/google-maps-logo.svg` in the module folder. Until it is there, the map shows the text "Google Maps"
   and the settings page shows a reminder.
3. In Zabbix, go to **Administration > General > Modules**, click **Scan directory** and enable
   **Google Maps for Zabbix**.

## Google Maps API key

1. In the [Google Cloud console](https://console.cloud.google.com/), create or pick a project and link a billing
   account.
2. Under **APIs & Services > Library**, enable **Map Tiles API**.
3. Under **APIs & Services > Credentials**, create an API key and restrict it:
   - **Application restrictions:** Websites, with your Zabbix address, for example `https://zabbix.example.com/*`.
   - **API restrictions:** Map Tiles API only.

   Every Zabbix user's browser receives the key (Google needs it in each tile request), so these restrictions are
   what protect it.
4. In Zabbix, open **Administration > General > Geographical maps**, paste the key, click **Test key**, then
   **Update**.

Only Super admins can change the key and the Layers button settings.

## Settings

| Setting | What it does |
| --- | --- |
| Tile provider | The default map of every Geomap. Google road map, satellite, hybrid and terrain are added at the top. |
| API key | Google Maps Platform key with the Map Tiles API enabled. |
| Language | Language of map labels, such as `en` or `en-AU`. |
| Region | Optional two-letter country code for borders and place names, such as `AU`. |
| Show Layers button | Adds the Layers button to every Geomap widget. |
| Maps in the Layers button | Which maps the button offers. The default map is always listed. |

## Costs and limits

Google bills Map Tiles API usage to your Cloud project. Check Google's
[pricing](https://developers.google.com/maps/documentation/tile/usage-and-billing) for the monthly free usage. A
project can load at most 15,000 2D tiles a day, and one map view loads roughly 20 to 40 tiles. The browser keeps each
Google session for about two weeks, so creating sessions doesn't add much usage.

Esri's tiles are free to try. Esri's terms may ask for an ArcGIS account for business use. To stop offering them,
untick them under *Maps in the Layers button*.

## How it works

- Zabbix only stores a built-in tile provider or "Other". A Google default map is saved as "Other" with a marker URL
  (`https://tile.googleapis.com/v1/2dtiles/{z}/{x}/{y}?geomaplayers=roadmap`). The module recognises that URL on the
  settings page and on dashboards.
- The module replaces the `geomaps.edit` and `geomaps.update` actions with its own versions of Zabbix 7.2's settings
  page. On dashboards, `assets/js/geomap-layers.js` hooks Leaflet's map and tile layer creation. It adds the Layers
  button, gets a Google session for Google layers, and swaps in the real Google URL.

**Before you disable or remove the module**, set *Tile provider* back to a built-in map. Stock Zabbix can't load
the Google marker URL.

## Licence

GNU Affero General Public License v3.0 (see [LICENSE](LICENSE)). The settings page is based on Zabbix's own
(Copyright Zabbix SIA, AGPL-3.0). Google Maps, the Google logo and Esri are trademarks of their owners. This project
isn't affiliated with Google, Esri or Zabbix.
