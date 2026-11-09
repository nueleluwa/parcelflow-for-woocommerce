# ParcelFlow for WooCommerce

[![CI](https://github.com/nueleluwa/parcelflow-for-woocommerce/actions/workflows/ci.yml/badge.svg)](https://github.com/nueleluwa/parcelflow-for-woocommerce/actions/workflows/ci.yml)
![Status](https://img.shields.io/badge/status-pre--release%200.1.0-orange)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)
![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-21759b)
![WooCommerce](https://img.shields.io/badge/WooCommerce-8.5%2B-96588a)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)

Connects a WooCommerce store to the **ParcelFlow** shipping API: live carrier rates at checkout, shipment booking after
payment, and delivery tracking for customers and staff.

WooCommerce stays in charge of products, cart, checkout, payment, accounts and orders. ParcelFlow supplies rates,
carriers, booking, labels and tracking.

> **Status: pre-release, not production-ready.**
>
> Version 0.1.0 has been linted, unit-tested, run through Plugin Check and security-probed on a throwaway WordPress site,
> but it has **never been run against the live ParcelFlow API**, and checkout (classic or Blocks), HPOS on/off and the
> admin screens have not been walked through in a browser.
> Booking also depends on details ParcelFlow has not confirmed, most importantly **where `rate_id` is returned**.
> Read [Known limitations](docs/KNOWN-LIMITATIONS.md) and [API questions](docs/API-QUESTIONS.md) before relying on it.

## Contents
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [How it works](#how-it-works)
- [What is and isn't supported](#what-is-and-isnt-supported)
- [Documentation](#documentation)
- [Development](#development)
- [Security](#security)
- [Contributing](#contributing)
- [License](#license)

## Features

### Checkout

- Native WooCommerce shipping method (works with shipping zones). Customers see carrier name, delivery estimate and price
  exactly as returned by ParcelFlow. Carrier logos and extra info are shown in the classic checkout.
- No hardcoded prices. If ParcelFlow is unreachable, returns nothing, or an item is missing weight/dimensions/HS code,
  the customer gets a clear message and **no rate**. A made-up price is never shown.
- Rates are cached briefly per exact payload (addresses, contents, weights, options); never across different ones.

### Products

- HS tariff code, optional declared value and customs description on the product *Shipping* tab. Weight and dimensions use
  the native WooCommerce fields.

### Booking

- Manual by default: an administrator reviews the paid order and books it. Optional automatic booking runs in the background
  (Action Scheduler).
- Duplicate protection is atomic (database-level). A timeout, 5xx or unreadable response is marked **uncertain** and is
  **never retried automatically**; an operator reconciles it against ParcelFlow's shipment history.

### Admin (ParcelFlow menu)

- **Shipments** – totals, status tabs, search by order/shipment/tracking number, date filter, pagination, CSV export, detail
  view with fees, package contents and tracking history.
- **Settings** – General, Sender/Warehouse, Shipping & Booking, Carriers/Services, Advanced.
- **Logs** – every API interaction with filters; tokens and personal data redacted.
- **Help / API Status** – setup checklist, environment info, troubleshooting, read-only connection test.

### Customers

- *Shipment Information* section on My Account → Orders → View, a *Track Shipment* tab, and a public `/track-shipment/` page.

## Requirements

| | Minimum |
|---|---|
| WordPress | 6.4 |
| WooCommerce | 8.5 (developed against 11.2.x) |
| PHP | 7.4 (with `sodium`, `openssl`, `mbstring`) |
| Network | Outbound HTTPS to the ParcelFlow API |
| ParcelFlow | An API access token (staging token for testing) |

## Installation

1. Get the plugin as a folder named `parcelflow-for-woocommerce`:
   - **ZIP:** zip the repository contents (without `vendor/`, `tests/`, `.github/`) into `parcelflow-for-woocommerce.zip`, or
   - **Git:** `git clone https://github.com/nueleluwa/parcelflow-for-woocommerce.git wp-content/plugins/parcelflow-for-woocommerce`
2. In WordPress: **Plugins → Add New → Upload Plugin**, choose the ZIP, then **Activate**
   (for the Git route, just activate it under Plugins).
3. Activation creates two database tables and a *Track Shipment* page at `/track-shipment/` (only if that slug is free).

Full steps: [docs/INSTALL-AND-CONFIGURE.md](docs/INSTALL-AND-CONFIGURE.md).

## Quick start

1. **ParcelFlow → Settings → General:** enable ParcelFlow, confirm the API base URL, paste your access token
   (stored encrypted), then **Help / API Status → Test connection**.
   *Only administrators can open Settings.*
2. **Sender / Warehouse:** enter the dispatch address (all fields are required by the API).
3. **Shipping & Booking:** keep **Booking mode = Manual**. Prefer a **fixed carton** size.
4. **WooCommerce → Settings → Shipping:** open a zone → *Add shipping method* → **ParcelFlow**.
5. **Products:** set weight, dimensions and the **HS tariff code** (Product data → Shipping).
6. Place a test order on staging. After payment, open **ParcelFlow → Shipments** and book it.

Tip: the access token can live in `wp-config.php` instead of the database:

```php
define( 'PARCELFLOW_WC_ACCESS_TOKEN', 'your-token' );
```

## How it works

```text
 Customer              WooCommerce                ParcelFlow plugin                 ParcelFlow API
    |  enters address     |                              |                                  |
    |-------------------->|  shipping package            |                                  |
    |                     |----------------------------->|  POST /calculate-shipment        |
    |                     |                              |--------------------------------->|
    |   carriers + prices |<-----------------------------|  carriers[] (selling_price...)   |
    |<--------------------|  WC_Shipping_Rate(s)         |                                  |
    |  pays               |                              |                                  |
    |-------------------->|  order → processing/completed|                                  |
    |                     |----------------------------->|  row "awaiting" (eligible)       |
    |                                                    |                                  |
  Admin books  ---------------------------------------->|  validate → lock → send once --->|  POST /create-shipment
                                                         |<---- shipment/tracking/label ----|
 Customer tracks ----------------------------------------|  GET /track-shipment ----------->|
```

Booking states: `awaiting → in_progress → booked`, or `failed` (definite rejection, safe to retry) or `uncertain`
(outcome unknown, needs reconciliation). Details: [docs/HOW-IT-WORKS.md](docs/HOW-IT-WORKS.md).

## What is and isn't supported

| | |
|---|---|
| Classic checkout | Implemented (rates, logos, ETA). Not yet verified in a live store. |
| Checkout Block | Rates flow through WooCommerce's standard shipping-rate API as text (“Carrier (3 to 5 working days)”). No logos. **Unverified.** |
| HPOS | Order data uses WooCommerce CRUD only. Compatibility is **not declared** until verified (filter `parcelflow_wc_declare_hpos_compatible`). |
| Cancel / re-book / re-issue label | Not possible: ParcelFlow documents no such endpoints. |
| Webhooks | None documented; tracking is polled. |
| Multiple packages, currency conversion | Not supported (one package per order; prices in one currency). |

See [docs/KNOWN-LIMITATIONS.md](docs/KNOWN-LIMITATIONS.md).

## Documentation

| Document | For |
|---|---|
| [Install & configure](docs/INSTALL-AND-CONFIGURE.md) | Store owners / admins |
| [How it works](docs/HOW-IT-WORKS.md) | Everyone: workflow, booking states, data stored |
| [Developer reference](docs/DEVELOPER-REFERENCE.md) | Hooks, filters, constants, options, meta, tables, jobs |
| [Known limitations](docs/KNOWN-LIMITATIONS.md) | Everyone |
| [API questions for ParcelFlow](docs/API-QUESTIONS.md) | ParcelFlow team / project owners |
| [Technical assessment](docs/TECHNICAL-ASSESSMENT.md) | Architecture and design decisions |
| [Testing](docs/TESTING.md) | QA: what was run, staging test plan |
| [Security audit](docs/SECURITY-AUDIT.md) | Security reviewers |
| [Changelog](CHANGELOG.md) | Release notes |

## Development

```bash
composer install
vendor/bin/phpcs      # WordPress coding standards (+ PHP 7.4 compatibility)
vendor/bin/phpunit    # unit tests (no WordPress needed)
```

Tests that need a real WordPress site live in `tests/integration` (database layer, run with `wp eval-file`) and
`tests/security` (HTTP probe of roles, escaping, customer isolation). See [docs/TESTING.md](docs/TESTING.md).
CI runs lint, PHPCS and PHPUnit on PHP 7.4, 8.1 and 8.3.

Source layout (`ParcelFlow\WooCommerce` namespace, PSR-4 under `src/`):

```text
src/Api/        Client (the 5 documented endpoints), Response, RateParser
src/Shipping/   ShippingMethod, RateService, PackageBuilder, PayloadMapper
src/Booking/    BookingService (lock, outcome classification, reconcile)
src/Orders/     OrderIntegration (payment hooks, order screen, customer view)
src/Tracking/   TrackingService, TrackingPage, Scheduler
src/Admin/      menu, pages, admin-post actions
src/Settings/   Settings (schema-driven), Crypto
src/Data/       Schema, ShipmentRepository, LogRepository
src/Support/    Logger, Redactor, Status, Format
```

## Security

Please report vulnerabilities privately; see [SECURITY.md](SECURITY.md). Never commit API tokens.
The latest review is in [docs/SECURITY-AUDIT.md](docs/SECURITY-AUDIT.md).

## Contributing

Issues and pull requests are welcome. Read [CONTRIBUTING.md](CONTRIBUTING.md) first.

## Authors

Emmanuel Egwa, Marvelous Benson, Eureka.

## License

GPL-2.0-or-later (see the plugin header in `parcelflow-for-woocommerce.php`).
