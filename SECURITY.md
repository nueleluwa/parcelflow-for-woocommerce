# Security policy

## Supported versions

This project is **pre-release (0.1.0)**. Only the latest commit on `main` receives security fixes.

## Reporting a vulnerability

**Please do not open a public issue or pull request for security problems.**

Use GitHub's private reporting: on the repository page go to **Security → Report a vulnerability**
(<https://github.com/nueleluwa/parcelflow-for-woocommerce/security/advisories/new>).

Please include:
- what you found and the impact (e.g. token disclosure, privilege escalation, data exposure);
- the plugin version / commit and your WordPress, WooCommerce and PHP versions;
- clear steps or a proof of concept, with any tokens or personal data **redacted**.

We aim to acknowledge reports within 5 working days and will keep you informed about the fix and disclosure timing.

## Scope

In scope: the plugin code in this repository.

Out of scope: vulnerabilities in WordPress, WooCommerce, other plugins/themes, or the ParcelFlow API/service itself
(report those to their respective maintainers).

## Security design (summary)

- The API access token is stored encrypted (libsodium) or supplied via `PARCELFLOW_WC_ACCESS_TOKEN`; it is never rendered,
  logged or sent to the browser. API calls are server-side, HTTPS-only, without redirects.
- Settings (API URL, token, booking options) require `manage_options`; operations require `manage_woocommerce`; every state-changing
  action verifies a nonce (bound to the order for booking actions).
- Customer shipment data is only shown to the order's owner (or a guest with a valid order key).
- Output is escaped; SQL uses prepared statements; logs redact tokens and personal data.

The latest review and its open items are in [docs/SECURITY-AUDIT.md](docs/SECURITY-AUDIT.md). It was a self-review and
has **not** been a substitute for an independent penetration test.

## For deployers

- Prefer defining the token in `wp-config.php`, use strong, unique salts, and rotate the token if it was ever shared.
- Keep Shop Manager accounts trusted (they can book shipments and view logs).
- Rate-limit the checkout AJAX/Store API routes and the public tracking page at your WAF/CDN.
