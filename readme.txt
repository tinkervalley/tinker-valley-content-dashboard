=== Tinker Valley Content Dashboard ===
Contributors: tinkervalley
Tags: acf, content, dashboard, editor
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 0.8.10
License: GPLv2 or later

A modern, focused content dashboard for WordPress and Advanced Custom Fields.

== Description ==

Tinker Valley Content Dashboard gives editors a clean interface at `/dashboard/`.
Administrators can choose visible post types, grid or list layouts, card fields, actions,
and whether editors may see the new-content button.

ACF is optional. When Advanced Custom Fields is active, fields assigned to each post type
are available in the card configuration and editor.

== Installation ==

1. Upload the `tinker-valley-content-dashboard` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Open **Content Dashboard** in the WordPress admin menu or visit `/dashboard/`.
4. Use the gear button to configure post types and card layouts.

== Changelog ==

= 0.8.10 =
* Add a top-bar button back to the standard WordPress dashboard.
* Restore a fixed, icon-only, horizontally scrollable mobile bottom navigation.

= 0.8.9 =
* Use query-based PWA icon, manifest, and service-worker URLs for hosts that reject virtual static-file paths.

= 0.8.8 =
* Serve dashboard app icons independently of WordPress permalink rewrite rules.

= 0.8.7 =
* Add a dedicated 180px Apple touch icon endpoint and explicit iOS icon metadata.
* Add configurable card and input background colors.

= 0.8.6 =
* Generate the PWA app icon from the configured dashboard brand, dark-brand, and page colors.
* Refresh the manifest, Apple touch icon, and service-worker cache when brand colors change.

= 0.8.5 =
* Make WordPress's forced update check refresh the GitHub release data too.
* Add a Check for updates link to the plugin row.
* Shorten successful and failed GitHub release cache durations.

= 0.8.4 =
* Automatically load additional content as the user scrolls.
* Show the number of loaded items alongside the complete result count.

= 0.8.3 =
* Fixed the custom Update URI hook to use the GitHub hostname.
* Fixed the custom update response format used by WordPress.
* Restored normal manual Update now notices for GitHub releases.

= 0.8.2 =
* Added Enable/Disable automatic updates directly to the plugin action links.
* Made the control available even when a host hides WordPress's Auto-updates column.

= 0.8.1 =
* Added an explicit automatic-update toggle to Dashboard Settings.
* Synchronized the toggle with WordPress's native plugin auto-update setting.
* Integrated GitHub releases with the WordPress background plugin updater.

= 0.8.0 =
* Added secure update checks against public GitHub releases.
* Added plugin information and release changelog integration in WordPress.
* Added automated, correctly structured release ZIP packaging for version tags.

= 0.7.1 =
* Replaced Themify with self-hosted Font Awesome Free 7.
* Added a simple Font Awesome class-name field with a live icon preview.
* Added dashboard-only navigation label overrides per post type.

= 0.7.0 =
* Moved the dashboard to `/dashboard/` with a compatibility redirect from the former URL.
* Replaced mobile bottom navigation with an accessible slide-in drawer.
* Added configurable Themify navigation icons per post type.
* Changed editor-field visibility to select-all-by-default with explicit unchecking.

= 0.6.0 =
* Added an installable Progressive Web App manifest and dashboard-scoped service worker.
* Added custom 192px and 512px stacked-card content icons.
* Added an install-app prompt where supported.
* Added offline messaging, safe-area support, bottom navigation, and mobile-first editor layouts.

= 0.5.2 =
* Render ACF Group fields as nested field panels instead of object text.
* Support nested groups, repeaters, choices, and media fields within ACF groups.

= 0.5.1 =
* Resolve ACF field groups against the exact post being edited.
* Support groups assigned by specific page, template, parent, taxonomy, and other ACF location rules.

= 0.5.0 =
* Added a dedicated administrator-only Site Settings page.
* Added site title, tagline, and native site-icon controls.
* Added editable scalar metadata registered by WordPress or plugins for REST use.
* Grouped compatible plugin metadata under Additional fields.

= 0.4.0 =
* Added dashboard sorting and per-post-type default sort settings.
* Added multi-select with bulk publish, draft, and trash actions.
* Added configurable editor field visibility per post type.
* Added navigation text and header background colors.
* Added a separate light logo for dark backgrounds.
* Added live page screenshots as an optional Pages card thumbnail.

= 0.3.0 =
* Added a custom front-end login screen for the content dashboard.
* Replaced the settings panel with a dedicated settings page.
* Added customizable dashboard colors and an optional logo.
* Added conditional post-type layout settings.
* Improved navigation styling, modal motion, and field-row alignment.

= 0.2.2 =
* Refined tab typography and widened the editing modal.
* Removed the duplicate custom media-frame heading.
* Aligned controls across fields with and without instructions.

= 0.2.1 =
* Fixed ACF tab panel visibility.
* Fixed native WordPress media-library loading.
* Added editable ACF repeater rows, including nested repeaters and media subfields.

= 0.2.0 =
* Matched the Tinker Valley color palette.
* Replaced the editor drawer with a centered modal.
* Added ACF field groups, tabs, wrapper widths, and richer choice controls.
* Added native WordPress media selection for image, gallery, and file fields.

= 0.1.0 =
* Initial release.
