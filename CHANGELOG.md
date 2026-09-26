# Changelog

All notable changes to WPAdverts_ARMember are documented here.

The project uses [Semantic Versioning](https://semver.org/).

## [0.5.0] - 2026-09-26

### Security

- Closed a frontend bypass: `?post_type[]=advert` archives and feeds listed adverts because WordPress never flags an array `post_type` query as an advert archive.
- Closed a REST bypass: WordPress matches REST routes case-insensitively, so `/wp-json/wp/v2/ADVERT` skipped the route check. Routes are now compared lowercased.
- The default access mode now requires ARMember's active status. ARMember adds every registered WordPress user to its member table, so the previous presence check admitted inactive, pending, and terminated accounts.
- Adverts and advert categories are removed from the core XML sitemap for unauthorized visitors.
- Single REST media items and comments attached to adverts are denied, and advert attachments and comments are removed from REST collections.
- Attachment pages of advert images redirect like the advert itself.
- oEmbed requests for adverts return 401 instead of 404. The plugin's own REST query filter hid the advert from its oEmbed check.
- Submitted advert IDs on WPAdverts forms are honored only for the advert owner or a user who can edit the advert, so another member's contact cannot be bound into a form.

### Added

- Logged-in visitor destination setting, so users who are signed in but lack access go to a page such as a membership plans page instead of the login page.
- Settings warning when the default mode is active, stating that it admits any active ARMember account, with an extra warning when open registration is enabled.
- The `wpaag_user_can_access` filter now also runs for anonymous visitors, with a user ID of 0.
- PHPUnit suite covering the access modes, REST routes, frontend detection, redirects, and contact ownership. CI runs it on PHP 8.0, 8.2, and 8.4.

### Changed

- Removed the hardcoded `login-2` default destination page. Sites that never saved settings now redirect to the WordPress login page until a destination is selected.
- Redirect targets are correct on subdirectory installs, where the path was previously doubled.
- The unauthorized destination page is never redirected away from itself.
- Settings are read once per request.
- Release packages no longer include `README.md`, `release-notes/`, or the social preview images.
- `release.sh` refuses to run outside `main`.

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

[0.5.0]: https://github.com/renatobo/wpadverts-armember/releases/tag/v0.5.0
[0.4.1]: https://github.com/renatobo/wpadverts-armember/releases/tag/v0.4.1
[0.4.0]: https://github.com/renatobo/wpadverts-armember/releases/tag/v0.4.0
[0.3.0]: https://github.com/renatobo/wpadverts-armember/releases/tag/v0.3.0
[0.2.0]: https://github.com/renatobo/wpadverts-armember/releases/tag/v0.2.0
