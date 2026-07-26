# Contributing

## Development

1. Install the plugin in a local WordPress site with WPAdverts and ARMember.
2. Make focused changes that preserve the access-control rules in `AGENTS.md`.
3. Run `composer lint`.
4. Run `./build.sh` and verify the generated ZIP.
5. Exercise the protected frontend and REST routes with logged-out, administrator, recognized-member, and valid-plan accounts as applicable.

## Pull requests

- Explain the behavior being changed and the security impact.
- Include the validation performed.
- Update documentation and release notes when behavior visible to administrators or visitors changes.
- Never include credentials, production data, exported member records, or private classified content.

## Security reports

Do not open public issues for suspected vulnerabilities. Follow `SECURITY.md`.
