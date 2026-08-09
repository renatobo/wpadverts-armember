=== WPAdverts_ARMember ===
Contributors: droc
Tags: wpadverts, armember, membership, access-control
Requires at least: 7.0
Tested up to: 7.0.2
Requires PHP: 8.0
Requires Plugins: wpadverts, armember-membership
Stable tag: 0.4.0
License: GPLv2 or later

Restricts WPAdverts pages, listings, category archives, and REST routes to ARMember users.

== Description ==

The default access mode permits administrators and logged-in users that exist in
ARMember's member table. A stricter setting requires an active ARMember account
with at least one effective, non-suspended membership plan.

Configure the access mode and unauthorized-visitor destination page under
Settings > WPAdverts_ARMember.

Protected frontend surfaces include:

* Individual `advert` posts
* The `advert` post type archive
* `advert_category` archives
* Pages containing WPAdverts list, search, category, publish, or manage blocks
* REST routes that expose adverts

Standard files in WordPress uploads remain directly accessible. This plugin
protects pages and REST data, not attachment-file URLs.

== Requirements ==

WP Adverts (`wpadverts`) and ARMember Lite (`armember-membership`) are declared
as required plugins, so WordPress blocks activation until both are active.

Sites running ARMember premium (the `armember` plugin folder) do not satisfy the
`armember-membership` dependency, because plugin dependencies are matched by
folder slug. Remove `armember-membership` from the `Requires Plugins` header on
those installations.

== Changelog ==

= 0.4.0 =
* Closed REST access-control bypasses through oEmbed, search, media, and comment routes.
* Removed adverts from frontend and REST search results for unauthorized visitors.
* Blanked WPAdverts blocks and shortcodes rendered from block-theme templates, template parts, patterns, and widgets.
* Denied WPAdverts admin-ajax actions, including the public contact and gallery endpoints, to unauthorized visitors.
* URL-encoded the `redirect_to` value on login redirects.
* Raised requirements to WordPress 7.0 and PHP 8.0, and declared WP Adverts and ARMember Lite as required plugins.

= 0.3.0 =
* Made Contact Person and Email read-only on frontend New Advert and Manage Advert forms.
* Synchronized advert contact metadata from the advert owner's ARMember/WordPress profile.
* Added display-name and first-and-last-name contact name sources.
* Prevented submitted read-only contact values from overriding profile data.

= 0.2.0 =
* Added ARMember-aware protection for WPAdverts pages, listings, taxonomies, blocks, shortcodes, and REST routes.
* Added recognized-user and valid-membership access modes.
* Added a WordPress page selector for the unauthorized-visitor destination.
* Added branded settings UI, GitHub/Git Updater metadata, artwork, and release documentation.
* Preserved administrators' access when ARMember is unavailable.

= 0.1.0 =
* Initial release.
