# Repository Instructions

## Scope

- This repository contains the `WPAdverts_ARMember` WordPress plugin.
- Keep runtime code compatible with PHP 7.4+ and WordPress 6.6+.
- Treat WPAdverts and ARMember as optional runtime dependencies: the plugin must fail closed for ordinary users and preserve administrator access when ARMember is unavailable.

## Access-Control Rules

- Protect WPAdverts frontend pages and advert REST routes consistently.
- The default mode accepts a logged-in WordPress user recognized by ARMember.
- The strict mode requires an active ARMember account and an effective, non-suspended plan.
- Preserve the `wpaag_user_can_access` and `wpaag_is_protected_page` filters.
- Unauthorized frontend visitors must be redirected to the configured WordPress page with the original URL in `redirect_to`.
- Unauthorized REST requests must return a structured `WP_Error` with HTTP 401.

## WordPress Engineering

- Guard directly loaded PHP files with `defined('ABSPATH') || exit;`.
- Check capabilities before rendering or changing administrator settings.
- Sanitize settings on write, escape output at render time, and use WordPress URL helpers.
- Use the `wpadverts-armember` text domain for all translatable strings.
- Do not protect raw attachment URLs; document that boundary instead of implying authenticated media protection.

## Versioning

- Keep these values synchronized:
  - `wpadverts-armember.php` plugin header `Version`
  - `WPAAG_VERSION`
  - `readme.txt` `Stable tag`
  - `CHANGELOG.md`
  - `release-notes/<version>.md`
- Use semantic versions in `X.Y.Z` format.

## Validation

- Run `composer lint` for PHP syntax checks.
- Run `./build.sh` and test the resulting archive before release.
- Verify logged-out classifieds redirect to the selected destination.
- Verify logged-out `/wp-json/wp/v2/advert` requests return HTTP 401.
- When changing access logic, test an administrator, a non-ARMember user, a recognized ARMember user, and a valid-plan user.

## Packaging and Distribution

- GitHub Releases is the primary distribution channel.
- Git Updater uses the metadata in `wpadverts-armember.php`.
- `./build.sh` creates `wpadverts-armember-<version>.zip`.
- Do not package Git metadata, development configuration, tests, release scripts, or existing ZIP files.
- Do not create a release without matching notes under `release-notes/`.
