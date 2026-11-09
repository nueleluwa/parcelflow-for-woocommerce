# Testing

## Automated (run in this repository, no WordPress)
```
composer install
vendor/bin/phpcs      # WordPress-Extra/Core/Docs + PHPCompatibilityWP (7.4+): 0 errors, 0 warnings
vendor/bin/phpunit    # 14 tests, 47 assertions
```
CI (`.github/workflows/ci.yml`) runs lint, PHPCS and PHPUnit on PHP 7.4, 8.1 and 8.3. CI has not run yet (no repository/remote exists); only the local PHP 8.3 run is verified.

PHPUnit covers pure logic with WordPress function stubs: money parsing (`"64,803.24"`), name sanitising, token encryption, rate parsing (**no fabricated `rate_id`**, `carrier_cost_price` dropped), status grouping, redaction, tracking event ordering, request URL/header/body shape, and booking outcome classification (400 retry-safe; 5xx, timeout, malformed body uncertain; DNS failure retry-safe; HTTP URL / missing token blocked). It does **not** cover the booking lock, the shipping method, admin screens or checkout.

PHPCS notes: docblock-style sniffs, hook-signature unused-parameter warnings and template-variable prefixing are deliberately relaxed in `phpcs.xml.dist`; table-name interpolation and nonce-via-`authorize()` have justified inline suppressions.


## Run on a real WordPress (throwaway site: WordPress 7.1.4, WooCommerce 11.2.2, PHP 8.2, MariaDB 10.11, Local's bundled binaries)
* **Plugin activation**: activates cleanly; both tables created. WP_DEBUG_LOG shows no ParcelFlow errors.
* **Plugin Check 2.2.0**: 0 errors, 0 warnings (initially 1 error + 71 warnings; fixed by using `%i` table placeholders, static filter SQL, removing `load_plugin_textdomain`, adding `languages/*.pot`, readme fixes; one remaining false positive is annotated inline).
* **Repository integration test** (`tests/integration/repository-test.php`, run with `wp eval-file`): 34 checks on real MariaDB - UNIQUE(order_id) idempotent create, atomic claim (second claim loses; booked/uncertain rows can never be claimed; failed can), stale in-progress -> uncertain, search/status/date filters, pagination, counts, SQL-injection string inert, log redaction in the DB, log filters, purge.
* **Still not run**: checkout (classic or Blocks), shipping method, booking against the ParcelFlow API, admin screens in a browser, My Account, tracking page, HPOS on/off. No ParcelFlow staging token was available.
**Not run:** anything needing WordPress/WooCommerce/MySQL, a browser, or the live ParcelFlow API. Treat everything below as *to do*.

## Environment
WordPress 6.4+/7.x, WooCommerce (record exact version), PHP 7.4 and 8.x, a ParcelFlow **staging** token, WP_DEBUG on. Test with HPOS **on and off**, with the classic checkout shortcode **and** the Checkout Block, and with a default block theme and a classic theme.

## Staging scenarios
| # | Scenario | Expected |
|---|---|---|
| 1 | Activate/deactivate/reactivate | No notices; tables + `/track-shipment/` created once; cron jobs removed on deactivate |
| 2 | Save settings with token; reload | Token never appears in page source or HTML; placeholder dots only |
| 3 | Test connection (valid / bad token) | OK / safe error message; logged without token |
| 4 | Local, interstate, international carts | Correct `shipment_type`; rates rendered with name, ETA, price |
| 5 | Multiple products, quantities | Weight = Σ + packaging; values per unit; one package |
| 6 | Product missing weight / dims / HS code | No rates, neutral customer message, reason in Logs |
| 7 | Change address/cart | Rates refresh; identical payload served from cache within the cache window |
| 8 | API down (wrong host) | “could not retrieve rates” – **no** price shown |
| 9 | API returns no carriers | “No ParcelFlow carriers available”; no false option |
| 10 | Pay → order | Row *Pending*; unpaid/failed/cancelled orders never bookable |
| 11 | Manual booking, double-click, two tabs | Exactly one API call; second attempt says duplicate/in progress |
| 12 | Booking without `rate_id` | Blocked with explanation; manual rate ID entry works once supplied |
| 13 | Simulated timeout (short timeout/blackhole proxy) | State *uncertain*, e-mail sent, no auto-retry; history lookup + link works; “confirm not created” re-enables booking |
| 14 | Tracking page: valid, unknown, malformed, API failure | Timeline sorted; friendly messages; no addresses shown |
| 15 | My Account: own order, other customer’s order | Own: section shown; other: WooCommerce denies; guest order-received needs key |
| 16 | HPOS on/off | Order meta, meta box, customer view all work. **Only then** enable `parcelflow_wc_declare_hpos_compatible` |
| 17 | Checkout Block | Rates appear/refresh and the selection reaches the order; document any gap before claiming support |
| 18 | CSV export | Opens cleanly; no formula injection |
| 19 | Logs | Filters work; no token/PII in context |
| 20 | Capability/nonce | Non-admin and missing-nonce requests to every `admin-post` action are refused |

## Record
| Date | WordPress | WooCommerce | PHP | HPOS | Checkout | Result |
|---|---|---|---|---|---|---|
| | | | | | | |

