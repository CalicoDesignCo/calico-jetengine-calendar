# Calico JetEngine Calendar

A reusable Elementor calendar combining up to four WordPress custom post type feeds. Each feed has its own JetEngine listing template, date fields, filters, and placement within the day.

## Features

- Up to four independent CPT feeds using standard WordPress posts and postmeta.
- Native JetEngine listing templates for entry content.
- Saved Advanced Date recurrence rows, Unix timestamps, and ISO date/time fields.
- Multi-day ranges and optional overnight placement on the starting day.
- Compatibility with Calico JetEngine Event Exclusions; the exclusion plugin is separate.
- Per-feed taxonomy, exact-meta, and include/exclude post ID filters.
- Month navigation, independent calendars, mobile agenda or month grid.
- Day cells, grid radius, weekday headings, separate month/year selectors, arrow controls, and mobile day heading styling.
- GitHub-backed updates through WordPress's standard plugin update screen. No GitHub token required.

## Requirements

WordPress 6.5+, PHP 8.1+, Elementor 3.20+, and JetEngine with Listings enabled. Elementor and JetEngine are not bundled. Advanced Date fields require JetEngine's calendar module.

## Install

Download [calico-jetengine-calendar.zip](https://github.com/CalicoDesignCo/calico-jetengine-calendar/releases/latest/download/calico-jetengine-calendar.zip). In WordPress choose Plugins > Add Plugin > Upload Plugin, upload the ZIP, and activate it. If replacing a development version, choose Replace current with uploaded; do not delete the existing plugin.

The installed folder and main file remain `calico-elementor-calendar/calico-elementor-calendar.php` for compatibility with existing calendars. The display name and Elementor widget name are Calico JetEngine Calendar.

## Configure

Add the Calico JetEngine Calendar widget in Elementor. Enable a feed, choose its CPT and published JetEngine Posts-source listing template, map its start/end meta fields, and select the correct storage mode. Repeat for additional feeds, up to four. Save the document before testing month navigation.

JetEngine Advanced Date uses local wall-clock timestamps. A blank end key defaults to the start field plus `__end_date`. Recurrences must already have saved occurrence rows covering the displayed months; this plugin does not regenerate recurrence rules. Feed options govern range placement and overnight display without changing source data.

Each feed defaults to 200 matching posts per visible 42-day window, adjustable to 500. The overall occurrence limit is 2,000. Complex listing templates and large postmeta tables affect performance; the feed cap is not a loading-speed guarantee. Full-page caches remain independent.

See [readme.txt](readme.txt) for date mapping, scope, limits, styling, and recovery details. JetEngine Custom Content Types, custom database storage, remote feeds, and Query Builder queries require adapters and are not supported sources in this release.

## Updates

Future stable releases are checked through the public GitHub Releases API and offered in WordPress. Use Dashboard > Updates > Check again to refresh. Automatic updates stay under the administrator's control. Drafts, prereleases, missing assets and downgrades are not offered. GitHub outages leave the installed calendar usable.

## Validation

The code has been exercised with PHP 8.3, a standalone PHP behavior harness, jsdom browser behavior checks, and a local WordPress 7.1.4 / Elementor 4.4.0 / JetEngine 3.8.3 integration environment. Development versions have also been used on a live Elementor 4.0.7 / JetEngine 3.7.7 site. These checks are not a guarantee that every third-party listing widget or hosting setup is compatible. Test complex interactive listings on the destination site first.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
