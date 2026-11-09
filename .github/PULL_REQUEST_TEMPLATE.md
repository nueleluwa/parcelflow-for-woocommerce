## What and why

<!-- What does this change, and why? Link the issue. -->

## How I tested it

- [ ] `vendor/bin/phpcs` passes (0 errors, 0 warnings)
- [ ] `vendor/bin/phpunit` passes
- [ ] Tested on a real WordPress + WooCommerce site (versions: …)
- [ ] Touches DB / access control / views → ran `tests/integration` and/or `tests/security`

**Not tested / limitations:** <!-- Be explicit. -->

## Checklist

- [ ] No undocumented API behaviour assumed (new questions added to `docs/API-QUESTIONS.md`)
- [ ] No tokens or personal data in code, logs, tests or screenshots
- [ ] Booking safety unchanged: one request per order, no auto-retry after an uncertain outcome
- [ ] Docs and `CHANGELOG.md` updated (Unreleased)
