# ParcelFlow for WooCommerce

Integrates ParcelFlow shipping services with WooCommerce: real-time shipping rates at checkout, carrier selection,
shipment booking and delivery tracking.

**Status: pre-release (0.1.0). Not production-ready.** Booking depends on API details ParcelFlow has not yet confirmed
(notably where `rate_id` is returned, the production URL, and wallet funding). See
[docs/API-QUESTIONS.md](docs/API-QUESTIONS.md) and [docs/KNOWN-LIMITATIONS.md](docs/KNOWN-LIMITATIONS.md).

Authors: Emmanuel Egwa, Marvelous Benson, Eureka.

## Features
* ParcelFlow as a native WooCommerce shipping method (live rates, no hardcoded prices, classic checkout; block checkout unverified)
* Product HS code / declared value / customs description
* Booking after payment: manual by default, optional automatic; atomic duplicate-booking protection; uncertain outcomes are never retried automatically
* Admin: shipments dashboard + CSV export, logs (redacted), settings, API status page
* Customer: shipment info in My Account, `/track-shipment/` page

## Requirements
WordPress 6.4+, WooCommerce 8.5+, PHP 7.4+.

## Install
Download `parcelflow-for-woocommerce.zip` (or zip the repository contents into a `parcelflow-for-woocommerce` folder) and upload it under
Plugins → Add New → Upload Plugin. See [docs/INSTALL-AND-CONFIGURE.md](docs/INSTALL-AND-CONFIGURE.md).

## Development
```
composer install
vendor/bin/phpcs        # WordPress coding standards
vendor/bin/phpunit      # unit tests
```
Integration and security checks that need a WordPress site are in `tests/integration` and `tests/security`
(see [docs/TESTING.md](docs/TESTING.md) and [docs/SECURITY-AUDIT.md](docs/SECURITY-AUDIT.md)).

## Security
Report vulnerabilities privately to the maintainers rather than in a public issue. Never commit API tokens.

## License
GPL-2.0-or-later.
