# WPAdverts_ARMember

![WPAdverts_ARMember](assets/wpadverts-armember-settings-banner.svg)

[![WordPress](https://img.shields.io/badge/WordPress-7.0%2B-21759B?logo=wordpress&logoColor=white)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![Tested up to](https://img.shields.io/badge/Tested%20up%20to-7.0.2-21759B?logo=wordpress&logoColor=white)](https://wordpress.org/)
[![Release](https://img.shields.io/github/v/release/renatobo/wpadverts-armember?label=release)](https://github.com/renatobo/wpadverts-armember/releases)
[![License: GPL v2 or later](https://img.shields.io/badge/License-GPL%20v2%20or%20later-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

WordPress plugin that protects WPAdverts classifieds with ARMember-aware access rules.

## Features

- Protects individual adverts, archives, categories, publishing pages, management pages, blocks, shortcodes, and advert REST routes.
- Default mode permits WordPress users recognized by ARMember.
- Optional strict mode requires an active ARMember account with an effective, non-suspended plan.
- Configurable destination page for unauthorized visitors.
- Profile-synchronized advert contact name and email.
- Configurable display-name or first-and-last-name contact source.
- Preserves administrator access if ARMember is unavailable.
- Includes Git Updater metadata for GitHub release installs.

## Requirements

- WordPress 7.0+
- PHP 8.0+
- WP Adverts (`wpadverts`)
- ARMember Lite (`armember-membership`)

Both are declared in the `Requires Plugins` header, so WordPress blocks
activation until they are installed and active.

Plugin dependencies are matched by folder slug, and ARMember premium installs as
`armember`, not `armember-membership`. On a premium site, drop
`armember-membership` from the `Requires Plugins` header in
`wpadverts-armember.php` and `readme.txt`; the runtime admin notice still
reports when ARMember is unavailable.

## Installation

1. Install the packaged zip from [GitHub Releases](https://github.com/renatobo/wpadverts-armember/releases).
2. Activate **WPAdverts_ARMember**.
3. Open **Settings → WPAdverts_ARMember**.
4. Choose the access requirement and unauthorized-visitor destination.

WPAdverts and ARMember must be installed and configured on the same WordPress site.

## Access modes

### Recognized ARMember user

Allows administrators and logged-in users present in ARMember's member table.

### Active account with a valid plan

Allows administrators and ARMember users whose account is active and who have at least one effective, non-suspended plan.

## Advert contact information

Frontend New Advert and Manage Advert forms display Contact Person and Email as read-only profile information. The plugin synchronizes those values from the advert owner's ARMember-associated WordPress account.

The contact name can use either:

- The ARMember/WordPress display name
- The account's first and last name, falling back to the display name when both are empty

The email remains stored in WPAdverts metadata so contact forms, notifications, and payment integrations continue to work.

## Privacy note

The plugin protects WordPress routes and REST data. Standard attachment URLs under `wp-content/uploads` remain directly accessible and should not be used for sensitive private files without a separate authenticated-media solution.

## Development

Requirements:

- PHP 8.0 or newer
- Composer 2 for metadata validation and the lint command
- `zip`, `unzip`, and `rsync` for packaging

Useful commands:

```bash
composer validate --strict --no-check-lock
composer lint
./build.sh
```

See [AGENTS.md](AGENTS.md) for repository-specific engineering and release rules and [CONTRIBUTING.md](CONTRIBUTING.md) for contribution guidance.

## Releases

Release notes live in [`release-notes/`](release-notes/). Packaged releases should use matching plugin-header and `readme.txt` versions.

GitHub Releases is the primary distribution channel. Pushing a semantic version tag such as `v0.3.0` runs the packaging workflow and attaches the installable ZIP to the release.

## Project files

- `wpadverts-armember.php` — plugin bootstrap and version metadata
- `includes/class-wpaag-plugin.php` — access rules and administrator settings
- `assets/` — administrator styles and project artwork
- `readme.txt` — WordPress plugin metadata and changelog
- `release-notes/` — release-specific notes
- `build.sh` — reproducible ZIP packaging
- `release.sh` — guarded tag and release workflow

## License

GPL v2 or later.
