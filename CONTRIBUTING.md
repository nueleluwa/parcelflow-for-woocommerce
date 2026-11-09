# Contributing

Thanks for helping. This plugin handles shipping bookings that can cost money, so changes are held to a high bar for
safety and honesty about what is and isn't verified.

## Ground rules

1. **Don't invent API behaviour.** The ParcelFlow Shipments API documentation is the source of truth. If a field,
   endpoint, ID or response shape isn't documented, isolate the dependency, document the question in
   [docs/API-QUESTIONS.md](docs/API-QUESTIONS.md), and don't guess. In particular, never derive or fabricate a `rate_id`,
   a price, or a shipment status.
2. **No hardcoded prices, carrier IDs or rate IDs.** They come from the API.
3. **Never expose or log the access token or personal data.** All logging goes through `Support\Logger`, which redacts.
4. **Booking must stay safe:** one request per order, no automatic retry after an uncertain outcome. Read
   [How it works → Booking](docs/HOW-IT-WORKS.md#3-booking) before touching `BookingService`.
5. **Use public WordPress/WooCommerce APIs.** Order data via WooCommerce CRUD only (HPOS-safe). Don't modify or copy
   WooCommerce core.
6. **Be honest in docs and PRs** about what you tested. Don't claim Checkout Blocks, HPOS or production readiness without
   having verified it.

## Setup

```bash
git clone https://github.com/nueleluwa/parcelflow-for-woocommerce.git
cd parcelflow-for-woocommerce
composer install
```

Requires PHP 7.4+ (CI runs 7.4, 8.1, 8.3). Keep code 7.4-compatible: no enums, `readonly`, `match`, named arguments,
union types or the nullsafe operator.

## Checks (all must pass)

```bash
vendor/bin/phpcs      # WordPress coding standards + PHP 7.4 compatibility, 0 errors and 0 warnings
vendor/bin/phpunit    # unit tests
```

If `phpcs` says the WordPress standard isn't installed (seen on Windows), register the paths once:

```bash
vendor/bin/phpcs --config-set installed_paths \
 "$PWD/vendor/wp-coding-standards/wpcs,$PWD/vendor/phpcsstandards/phpcsutils,$PWD/vendor/phpcsstandards/phpcsextra,$PWD/vendor/phpcompatibility/php-compatibility,$PWD/vendor/phpcompatibility/phpcompatibility-paragonie,$PWD/vendor/phpcompatibility/phpcompatibility-wp"
```

Use `vendor/bin/phpcbf` to auto-fix formatting. Suppress a sniff only with an inline comment that says **why**
(e.g. `// phpcs:ignore … -- reason`). Docblock-style sniffs are relaxed in `phpcs.xml.dist`; correctness and security sniffs are not.

### Tests that need WordPress
Unit tests stub WordPress and cover pure logic. For anything touching the database, hooks, admin screens or access control,
also run the integration/security checks on a real site (WordPress + WooCommerce + MariaDB/MySQL; Local by Flywheel works):

- `tests/integration/repository-test.php`: `wp eval-file` (database layer, booking lock).
- `tests/security/seed.php` + `tests/security/probe.py`: roles, nonces, escaping, customer isolation.

See [docs/TESTING.md](docs/TESTING.md). Add or extend a test for every bug you fix.

### Plugin Check
Before submitting larger changes, run the official **Plugin Check** plugin (`wp plugin check parcelflow-for-woocommerce`).
The current baseline is 0 errors and 0 warnings.

## Code style notes

- Namespace `ParcelFlow\WooCommerce`, one class per file under `src/` (PSR-4).
- Prefix everything global: `parcelflow_wc_*` (options/hooks), `_parcelflow_*` (meta), `PARCELFLOW_WC_*` (constants).
- Escape late (`esc_html`, `esc_attr`, `esc_url`), sanitise on input, verify a capability **and** a nonce for every
  state-changing admin action. Use `%i`/`%s`/`%d` placeholders with `$wpdb->prepare()`, never string-built SQL.
- Strings are translatable with the text domain `parcelflow-for-woocommerce`. Regenerate the `.pot` with
  `wp i18n make-pot . languages/parcelflow-for-woocommerce.pot --exclude=vendor,tests,.github` when strings change.
- Settings: add fields to `Settings::schema()`.

## Pull requests

1. Branch from `main`; keep PRs focused.
2. Describe *what* and *why*, and how you tested it (including what you could **not** test).
3. Update [CHANGELOG.md](CHANGELOG.md) under **Unreleased**, and the docs if behaviour changed.
4. Make sure CI is green.

## Reporting bugs and requesting features
Use the issue templates. For security problems **do not open a public issue**; see [SECURITY.md](SECURITY.md).
Never paste access tokens, full API payloads or customer data into issues. Redact them first.
