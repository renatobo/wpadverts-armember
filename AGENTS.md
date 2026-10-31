# Repository Instructions

## Scope

- This repository contains the `WPAdverts_ARMember` WordPress plugin.
- Keep runtime code compatible with PHP 8.0+ and WordPress 7.0+.
- Treat WPAdverts and ARMember as optional runtime dependencies: the plugin must fail closed for ordinary users and preserve administrator access when ARMember is unavailable.

## Access-Control Rules

- Protect WPAdverts frontend pages and advert REST routes consistently.
- The default mode accepts a logged-in WordPress user whose ARMember account is active. ARMember registers every WordPress user as active, so presence in its member table is not a membership signal.
- The strict mode requires an active ARMember account and an effective, non-suspended plan.
- Preserve the `wpaag_user_can_access` and `wpaag_is_protected_page` filters.
- Unauthorized frontend visitors must be redirected to the configured WordPress page with the original URL in `redirect_to`. Logged-in visitors use the logged-in destination when one is set.
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
- Run `composer test` for the PHPUnit access-control suite, and add a test for every access-rule change.
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

## GitHub releases after PR merge

The supported release path is `.github/workflows/release.yml`. Ordinary merges do not publish. The old tag/push/manual publishing workflows have been replaced; pushing a tag alone does not publish.

For the next release:

1. Bump `wpadverts-armember.php` header `Version` and `WPAAG_VERSION` to matching `X.Y.Z`. Also update `readme.txt` Stable tag and any Version field. Update the user-facing changelog/upgrade notes under this repository’s conventions.
2. Add `release-notes/X.Y.Z.md` with exact headings `## New Features`, `## Improvements`, `## Bug Fixes`; use `- None.` if a section has no entries. Older releases without this notes format remain unchanged.
3. Open a PR into `main`, run all required checks, then apply **`release` before merging**. That label explicitly opts the merge into publication. Do not create/push the version tag yourself or use the retired direct-tagging `release.sh` path.
4. Merge. The workflow verifies the canonical merged PR and exact merge commit, runs all existing reusable quality gates plus release/package guards, checks versions/notes and main ancestry, creates annotated `vX.Y.Z`, and publishes the **exact CI-tested ZIP** with SHA-256 checksum. The release is created as a draft and published only after asset upload succeeds.
5. Read back the published release and uploaded checksum; a green PR is not evidence that publication completed. Expected ZIP: `wpadverts-armember-{version}.zip` under root `wpadverts-armember/`.

If publication fails, manually dispatch **Release merged PR** from `main` with `pr_number`. The PR must be merged into `main` and still carry `release`. Adding the label after merge does not trigger publication, but manual dispatch can release that eligible PR. Matching existing tags may be reused only when they point to the same merge commit. Existing releases/drafts are never overwritten: inspect and explicitly resolve partial drafts before retrying; never move/delete published tags as a retry strategy. Concurrent runs for the same PR are serialized.

Tags and publication happen in one workflow, avoiding the `GITHUB_TOKEN` tag-event suppression. No PAT or additional secret is required. Only publishing has `contents: write` ; quality jobs retain their own minimum analysis permissions.

Release configuration is `.github/release.json`; guard code is `.github/scripts/release.py`; tests run with `python3 -B -m unittest discover -s .github/tests -v`. Package locally with `bash build.sh`, then run `python3 -B .github/scripts/release.py package`. Release ZIPs exclude this tooling and agent guides. Do not hand-edit the upload/download handoff to rebuild a different artifact in the publishing job.

For workflow-only maintenance, keep the plugin version unchanged and omit the `release` label. Merge setup/automation PRs unlabelled; the next labelled version PR uses the new flow.
