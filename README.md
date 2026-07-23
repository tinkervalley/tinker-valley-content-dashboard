# Tinker Valley Content Dashboard

A modern, mobile-friendly WordPress content dashboard for editing posts, pages, custom post types, ACF fields, and compatible registered metadata.

## Highlights

- Focused front-end dashboard at `/dashboard/`
- Pages, posts, and custom post types
- ACF groups, tabs, repeaters, media, and contextual location rules
- Configurable card layouts, sorting, editor fields, labels, and icons
- Bulk content actions
- Site identity and dashboard appearance settings
- Installable PWA with responsive mobile navigation
- Automatic updates from public GitHub releases

## Installation

Download `tinker-valley-content-dashboard.zip` from the latest GitHub release and upload it through **Plugins → Add New → Upload Plugin** in WordPress.

## Updates

The plugin checks the latest public GitHub release using the WordPress HTTP API. Stable releases containing the `tinker-valley-content-dashboard.zip` asset appear in the normal WordPress Updates interface.

## Development

Create a version tag to build and publish an installable release:

```bash
git tag v0.8.0
git push origin v0.8.0
```

The release workflow packages the repository inside the required `tinker-valley-content-dashboard/` plugin directory.

## License

GPL-2.0-or-later. Font Awesome Free assets retain their upstream license in `assets/vendor/fontawesome/LICENSE.txt`.
