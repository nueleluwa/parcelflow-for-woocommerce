# Changelog

All notable changes are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
the project aims to follow [Semantic Versioning](https://semver.org/) once it reaches 1.0.

## [Unreleased]

### Added
- GitHub documentation: expanded README, how-it-works guide, developer reference, contributing guide, security policy,
  issue and pull-request templates.

## [0.1.0]: pre-release

First development release. **Not production-ready**; see [docs/KNOWN-LIMITATIONS.md](docs/KNOWN-LIMITATIONS.md).

### Added
- ParcelFlow WooCommerce shipping method with live rates, short payload-keyed caching, carrier logos/ETA in classic checkout.
- API client for the five documented Shipments API endpoints; `rate_id` adapter that never guesses.
- Product fields: HS tariff code, declared value, customs description.
- Shipment booking: manual (default) and automatic modes, atomic duplicate protection, uncertain-outcome classification and
  operator reconciliation via shipment history.
- Admin: Shipments dashboard (filters, pagination, CSV export, detail view), tabbed Settings, redacted Logs,
  Help / API Status with read-only connection test, order-screen meta box.
- Customers: My Account shipment section and *Track Shipment* tab, public `/track-shipment/` page.
- Action Scheduler jobs for tracking refresh, stale-booking sweep and log purge.
- Encrypted token storage (libsodium) and `PARCELFLOW_WC_ACCESS_TOKEN` constant support.
- Tooling: PHPCS (WordPress standards), PHPUnit, GitHub Actions CI (PHP 7.4 / 8.1 / 8.3), database integration test, security probe.

### Security
- API settings (base URL, token, booking options) restricted to `manage_options` so Shop Managers cannot redirect the token.
- Filter `parcelflow_wc_client_ip` for the tracking throttle behind proxies.
- Full list: [docs/SECURITY-AUDIT.md](docs/SECURITY-AUDIT.md).

[Unreleased]: https://github.com/nueleluwa/parcelflow-for-woocommerce/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/nueleluwa/parcelflow-for-woocommerce/commits/main
