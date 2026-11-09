# Security audit (v0.1.0)

Scope: the plugin source, exercised on a throwaway WordPress 7.1.4 + WooCommerce 11.2.2 site (PHP 8.2, MariaDB 10.11) with
five roles (anonymous, subscriber, shop manager, two customers, administrator).
Not covered: a live ParcelFlow API, a production host/WAF, penetration testing by a third party.

Re-run: `tests/security/seed.php` (via `wp eval-file`) creates the data, then
`python tests/security/probe.py <order_id> <other_order_id> <order_key>` runs 34 HTTP checks against `127.0.0.1:8099`.

## Findings

| # | Severity | Finding | Status |
|---|---|---|---|
| 1 | **High** | **Shop Managers could redirect the API token.** Settings required only `manage_woocommerce`. A Shop Manager could change the API base URL (confirmed by the probe: save accepted, HTTP 302) and every later API call would send the `ACCESS_TOKEN` header to a server they control. | **Fixed.** Settings page and save handler now require `manage_options` (`Admin::CAP_SETTINGS`). Shipments, Logs, Help and booking stay on `manage_woocommerce`. |
| 2 | Low | Tracking throttle keys on `REMOTE_ADDR`; behind a proxy/CDN every visitor shares one address, so one visitor could exhaust the limit for all. | **Mitigated.** New filter `parcelflow_wc_client_ip` to supply the trusted client IP. |
| 3 | Low (design) | Shipment numbers look sequential (`PF-2026-89472`). Anyone who guesses one issued by this store sees its status/event timeline (never addresses, phone or e-mail). Throttled to 15 lookups/min/IP, unknown numbers are rejected locally without an API call. | **Accepted.** If event text is sensitive for your business, require the order e-mail alongside the number (not implemented). |
| 4 | Low | Anonymous visitors can trigger ParcelFlow rate calculations (cart/checkout with varied addresses), which could consume API quota. Mitigated only by per-payload caching and WooCommerce's own session caching. | **Open.** Consider a WAF/rate limit on `?wc-ajax=update_order_review` and Store API routes. |
| 5 | Low | Token encryption key derives from the site salts; default/weak salts weaken it. Define `PARCELFLOW_WC_ACCESS_TOKEN` in `wp-config.php` for stronger isolation. | **Documented.** |
| 6 | Info | Rate metadata (`carrier_id`, `rate_id`, selling price) is stored on the order shipping line and is visible to the customer through the Store API. `carrier_cost_price` is never stored or exposed. | By design. |
| 7 | Info (functional) | The guest order-received ("thank you") page shows no shipment section on block-based themes (the hook is not fired there). Not a leak; the section appears in My Account. | Open (functional). |
| 8 | Info | `docs/` and `languages/` ship inside the plugin ZIP and are web-readable. They contain no secrets. | Accepted. |

## Verified controls (all passing in the probe)

* **Authentication / authorisation**
  * Anonymous requests to every `admin-post` action are not handled (no `nopriv` hooks).
  * Subscribers are denied the Shipments and Settings pages, settings save, and CSV export.
  * Shop Managers: no settings access (post-fix); state-changing actions refused without a nonce.
  * Nonces are bound to the order (a nonce for order A is refused for order B).
* **Customer isolation**: customer A sees own shipment data; customer B, anonymous visitors, and guest requests with no or a wrong order key do not.
* **Output escaping (stored XSS)**: hostile strings in carrier name, tracking number, status, event text, log message/context and a `javascript:` label URL were rendered escaped / not linked in admin list, detail, logs, order screen, My Account and the public tracking page.
* **Secrets**: the access token never appears in the Settings, Help, Logs, order or tracking HTML; log context redacts PII keys and e-mail addresses and scrubs the token from free text (also verified in the database).
* **Injection**: tracking input with script tags and SQL quotes is inert; all SQL uses `$wpdb->prepare()` with `%i`/placeholders (34 DB integration checks incl. an injection string); LIKE input is escaped.
* **Input limits**: oversized tracking input rejected; per-IP throttle engages.
* **Transport**: API base URL must be HTTPS; redirects are not followed; `reject_unsafe_urls` blocks private/loopback targets.
* **Other**: CSV export neutralises formula injection; duplicate booking protection is atomic (UNIQUE key + conditional UPDATE, tested on MariaDB); every PHP file exits without `ABSPATH`.

## Recommendations before production
1. Keep Shop Manager accounts trusted: they can book shipments (which may debit the ParcelFlow wallet) and see logs.
2. Use the `wp-config.php` token constant and strong salts; rotate the token if it was ever pasted into a ticket/chat.
3. Put a rate limit in front of checkout AJAX and the tracking page.
4. Re-run `tests/security/probe.py` after any change to capabilities, views or the tracking page.
