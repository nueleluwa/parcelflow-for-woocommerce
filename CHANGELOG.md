# Changelog

All notable changes are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
the project aims to follow [Semantic Versioning](https://semver.org/) once it reaches 1.0.

## [Unreleased]

### Changed (API documentation is the only source of truth)
- **Shipment type must now be chosen** in Settings; there is no default and no rates are requested until it is set. Auto-detect is opt-in, labelled experimental and not defined by the API documentation.
- **Package dimensions default to a fixed carton** entered by the administrator; the product-based estimate is opt-in and labelled experimental.
- **No invented `state`:** countries without a WooCommerce state list no longer have the city substituted; rates are blocked with "state is missing".
- **Dashboard status grouping** recognises only the statuses named in the documentation (Created, Pending, In Transit, Delivered); other values are shown verbatim under Processing. Only Delivered is treated as final.

### Added
- `docs/API-CONFORMANCE.md`: field-by-field mapping of everything sent/read to the API documentation, plus every place the documentation is silent and what the plugin does instead.
- ParcelFlow history (API) tab on Shipments: read-only browser for `get-shipment-history` with the documented date range, status and pagination parameters.
- Optional package override (weight/length/width/height) on the booking form, recorded in an order note; all four values or none.
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
