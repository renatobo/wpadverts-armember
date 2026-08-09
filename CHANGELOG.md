# Changelog

All notable changes to WPAdverts_ARMember are documented here.

The project uses [Semantic Versioning](https://semver.org/).

## [0.4.1] - 2026-08-09

### Changed

- Renamed the displayed plugin name to "WP Adverts <> ARMember" on the Plugins screen, Settings menu, and settings page. The plugin directory, main file, text domain, settings page slug, and option name are unchanged.

## [0.4.0] - 2026-08-09

### Security

- Closed REST access-control bypasses: oEmbed, `/wp/v2/search` with an `advert` subtype, and `/wp/v2/media` and `/wp/v2/comments` queries scoped to an advert are now denied to unauthorized visitors.
- Adverts are removed from frontend and REST search results for unauthorized visitors instead of leaking titles and excerpts.
- WPAdverts blocks and shortcodes are now blanked wherever they render, including block-theme templates, template parts, synced patterns, and widgets, which the previous `post_content` scan could not see.
- WPAdverts admin-ajax actions, including the public `adverts_show_contact`, contact form, and gallery endpoints, are denied to unauthorized visitors. They previously bypassed every access check.
- The `redirect_to` value on login redirects is URL-encoded.

### Changed

- Minimum supported WordPress version raised to 7.0 and minimum PHP version raised to 8.0.
- Declared `Requires Plugins: wpadverts, armember-membership`, so the plugins page lists WP Adverts and ARMember Lite as dependencies and blocks activation until both are active. Sites running ARMember premium (`armember` folder) must remove the `armember-membership` entry, since dependencies match by folder slug.
- Blocked WPAdverts blocks and shortcodes render a member-only notice in place of their content.

## [0.3.0] - 2026-07-25

### Added

- Configurable display-name or first-and-last-name source for advert contacts.
- Profile synchronization for WPAdverts contact metadata.

### Changed

- Contact Person and Email are visible but read-only on frontend advert publishing and management forms.
- Submitted read-only values are replaced with the advert owner's account values.

## [0.2.0] - 2026-07-25

### Added

- ARMember-aware access control for WPAdverts frontend and REST surfaces.
- Recognized-user and valid-membership access modes.
- Configurable unauthorized-visitor destination page.
- Branded administrator interface and Git Updater metadata.
- Project artwork, release documentation, security policy, and packaging workflow.

[0.4.1]: https://github.com/renatobo/wpadverts-armember/releases/tag/v0.4.1
[0.4.0]: https://github.com/renatobo/wpadverts-armember/releases/tag/v0.4.0
[0.3.0]: https://github.com/renatobo/wpadverts-armember/releases/tag/v0.3.0
[0.2.0]: https://github.com/renatobo/wpadverts-armember/releases/tag/v0.2.0
