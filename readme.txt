=== Calico JetEngine Calendar ===
Contributors: calico
Tags: calendar, elementor, jetengine, events, custom post types
Requires at least: 6.5
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A reusable Elementor calendar with up to four independent CPT feeds and JetEngine listing templates.

== Description ==

Combine events, opening hours, specials, or other dated custom post types in one calendar. Each feed has its own post type, published JetEngine listing template, start and end meta fields, date storage mode, filters, and position within the day.

* Four feeds maximum, enforced by the editor and server.
* Standard WordPress postmeta, including saved JetEngine Advanced Date recurrence rows.
* JetEngine local timestamps, actual Unix timestamps, or ISO date/date-time text.
* Multi-day ranges, optional inclusive date-only ends, and optional overnight display on the starting day.
* Calico JetEngine Event Exclusions compatibility using its saved per-field exclusion map.
* Published, non-password-protected posts and listing templates only.
* Independent month/year navigation for multiple calendar widgets on a page.
* Mobile agenda or month grid; Elementor styling controls for the calendar and each feed.
* Separate Style sections for day cells, weekday headings, the grid outer border, month and year selectors, and shared/individual arrows.
* Optional Calendar Occurrence Start/End dynamic text tags for recurrence-aware listing content.

Elementor 3.20 or later and JetEngine with Listings enabled are required. JetEngine is not bundled.

== Styling ==

In Elementor, select Calico Calendar and open Style. Days includes individual cell radius, background colors, hover/today/adjacent-month colors, borders, padding, and date badge options. Grid Outer Border controls the outside border and radius; optional day spacing makes rounded cells distinct. Weekday Headings has its own colors and typography.

Month Selector and Year Selector each have independent typography, normal/hover/focus/disabled colors, backgrounds, borders, radius, width, height, and padding. Arrows — Shared styles both navigation buttons. Previous Arrow and Next Arrow override those shared styles independently, including icon size, dimensions, radius, and interaction colors. Blank controls inherit the existing shared navigation styles.

== Installation ==

1. Upload the ZIP under Plugins > Add New > Upload Plugin, and activate it on a test site first.
2. Edit a page in Elementor and add the Calico JetEngine Calendar widget.
3. Enable Feed 1, choose the post type and its JetEngine listing template, and enter the start date meta key.
4. Choose the correct date storage mode. Configure an end field, range behavior, filters, and additional feeds as needed.
5. Save the page before testing month navigation. Navigation reads the saved widget settings.

== Date mapping ==

JetEngine local timestamp / Advanced Date: use for numeric values encoding local wall-clock time as UTC. A blank end key automatically uses START_KEY__end_date. Each saved start row is paired with the end row at the same index.

Unix timestamp: use only when the stored number represents a real UTC instant. It is converted into the WordPress site timezone.

ISO text: supports YYYY-MM-DD and YYYY-MM-DD HH:MM[:SS], with T separators and optional Z or numeric offsets. Natural-language dates are rejected.

A midnight end is exclusive by default. Enable "Include a midnight end date" for date-only ranges whose final day should be included. "Show on every date in its range" controls whether an occurrence spans several dates or appears only on its starting date.

"Keep overnight entries on starting date" applies to an end before noon on the following day. It changes calendar placement only; the actual end remains available to the occurrence tag, and no post metadata or event copy is changed.

== Recurrence and exclusions ==

The widget reads saved occurrence rows. It does not generate recurring dates from an Advanced Date configuration. Recurring content must have materialized start/end dates covering the months you want to display. This preserves dates removed by the Calico exclusion plugin. Its _calico_jetengine_exclusions map is also checked per start-field key.

Normal JetEngine Dynamic Fields retain their usual behavior, including returning the first value of a repeated meta key. Use the optional Calico Calendar Occurrence Start/End dynamic text tags where available if a listing needs the occurrence currently being rendered.

== Scope and limits ==

Feeds read standard WordPress posts and postmeta. JetEngine Custom Content Types (CCT), custom tables/custom meta storage, remote APIs, and Query Builder custom queries are not sources in this version. They require adapters. Listing templates should use the Posts source and match their feed's CPT.

Each feed defaults to a maximum of 200 matching posts for the visible 42-day range, adjustable up to 500. The calendar caps occurrences at 2,000. A visible notice appears if a limit is exceeded; narrow the filters rather than silently dropping content. Limits bound processing but do not guarantee a particular loading speed. Complex listing templates and large postmeta tables still affect performance.

Only matching post IDs are cached, for 60 seconds by default. Rendered listing HTML is not persistently cached by this plugin. Saves invalidate the ID cache. Full-page/server caches remain independent; purge them after changing calendar settings or content.

Calendar requests use a signature bound to a saved widget and document. Changing those settings invalidates the previous signature. An editor preview can show unsaved settings, but month navigation requires saving and reloading first. Public requests cannot load draft/private/password-protected documents or arbitrary browser-supplied feeds.

Custom scripts in listing HTML are not executed through innerHTML. Enqueued assets are loaded and Elementor widget ready hooks run after navigation. Third-party listing widgets that require a specific Listing Grid wrapper or their own custom AJAX initialization may need an integration adapter. Test interactive templates on the intended site.

== Changelog ==

= 1.0.0 =
* First public release as Calico JetEngine Calendar, including GitHub-backed WordPress updates.
* Includes four feeds, independent post inclusion/exclusion filters, and mobile day heading styling.
* Preserves the original calico-elementor-calendar folder, PHP basename, widget ID, settings and date metadata for existing installations.

= 0.2.0 =
* Added independent day backgrounds, borders, corner radius, padding, date badge styling, and weekday heading colors.
* Added an outer grid border and radius, with optional spacing between individual days.
* Added separate month/year typography, colors, backgrounds, borders, radius, dimensions, hover, focus, and disabled styling.
* Added shared arrow controls and independent previous/next arrow overrides.
* Existing settings and default appearance remain compatible.

= 0.1.0 =
* Initial local build: four CPT feeds, per-feed JetEngine listings and date mapping, month navigation, styling, recurrence rows, exclusions, and overnight placement.

== GitHub updates ==

Releases: https://github.com/CalicoDesignCo/calico-jetengine-calendar/releases

Install this ZIP once, then future stable GitHub releases appear in WordPress's normal plugin update screen. Older development versions without an updater need this one-time manual ZIP replacement. Do not delete the existing plugin. Automatic updates remain under the site administrator's control.

Only normal published releases with the expected calico-jetengine-calendar.zip asset are offered. No GitHub token is needed. Failed checks are cached briefly and do not interrupt calendar use. Dashboard > Updates > Check again refreshes the release cache. The internal plugin folder remains calico-elementor-calendar for upgrade compatibility.

Mobile Day Heading controls the agenda date label's background, text color, typography and size, border, padding, and corner radius. It affects only the mobile agenda. Include Post IDs and Exclude Post IDs restrict individual feeds without modifying source posts.
