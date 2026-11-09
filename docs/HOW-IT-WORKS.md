# How it works

## 1. Rates at checkout

1. WooCommerce builds a shipping package for the cart and calls `ShippingMethod::calculate_shipping()`.
2. The plugin needs: destination country + address, receiver name, phone and e-mail, a configured sender, and every
   shippable item having **weight, HS code** and (unless a fixed carton is set) **dimensions**. Anything missing means *no
   rate*, a neutral message for the customer, and a reason in **ParcelFlow → Logs** (“Rates not requested: …”).
3. `PackageBuilder` turns items into one carton: weight = Σ(unit weight × quantity) + packaging weight;
   dimensions = the fixed carton, or *longest length, widest width, heights stacked* (never a blind sum).
4. `PayloadMapper` builds the documented `calculate-shipment` payload. Shipment type is auto-detected (different country →
   International; same country and state → Local; same country, different state → Interstate) unless pinned in settings.
5. `RateService` calls the API (or serves a short-lived cached copy), parses carriers, drops any without an ID or parseable
   `selling_price`, and applies the carriers you've hidden in Settings.
6. Each carrier becomes a `WC_Shipping_Rate` whose metadata (`_parcelflow_*`) is copied by WooCommerce onto the order's
   shipping line when the order is placed. That is how the chosen carrier and price are retained.

`carrier_cost_price` from the API is discarded immediately and never stored or shown.

## 2. After payment

When an order reaches **Processing** or **Completed** and uses ParcelFlow shipping, the plugin creates a row in
`{prefix}parcelflow_shipments` in the state **awaiting** and adds an order note. Unpaid, failed, cancelled and refunded
orders never get one and can never be booked.

## 3. Booking

`BookingService::book()` does, in this order:

1. **Eligibility:** plugin enabled and configured, order paid, order uses ParcelFlow.
2. **Refuse duplicates:** already `booked`, `in_progress` or `uncertain` → stop.
3. **Validate everything** (carrier/rate retained, `rate_id`, sender, receiver, package, currency, pickup/insurance options).
   A validation problem never consumes the lock.
4. **Take the lock:** one conditional `UPDATE … WHERE state IN ('awaiting','failed')` on a row with `UNIQUE(order_id)`.
   Only one request can win.
5. **Send `create-shipment` exactly once.**
6. **Classify the outcome:**

| Outcome | Result state | Retry? |
|---|---|---|
| 2xx, `success: true`, with a shipment reference | `booked` | – |
| 4xx with `success: false`, or the request never left (DNS/connection refused) | `failed` | Yes, explicit |
| Timeout, 5xx, malformed body, “success” without a reference, crash mid-request | `uncertain` | **No**: reconcile first |

On success the plugin stores shipment ID, number, tracking number, label URL, status and total fee, in the table and as
order meta, and adds an order note.

### `rate_id` (the open blocker)
Create Shipment requires `booking.rate_id`, but the documented rate response doesn't include one. The plugin saves a
`rate_id` **only if the API literally returns that key** in a carrier object. It never guesses or derives it. If none is
available, booking is blocked, and an administrator can enter the rate ID supplied by ParcelFlow on the shipment page.

### Reconciling an uncertain booking
Open the shipment (ParcelFlow → Shipments → View):
- **Look up recent shipments** (one read-only call to `get-shipment-history`), pick the matching one and **Link**, or
- tick the confirmation box that you verified **no shipment exists** and **Allow a new booking attempt**.

A booking left `in_progress` for over 10 minutes (e.g. a crash) is moved to `uncertain` by the background job.

## 4. Tracking

- **Polling:** Action Scheduler checks open shipments (default every 6 hours each; configurable, 0 = off).
- **Customers:** My Account order view, the *Track Shipment* tab, or `/track-shipment/`. The lookup only resolves
  shipments booked through this store (shipment *or* tracking number), shows status and events only (never addresses),
  validates input, caches for 3 minutes and is throttled per IP. The API is always queried by shipment number.
- Events are shown as returned, in chronological order. The plugin never invents a status; an empty API status never
  overwrites a known one.

## 5. Roles

| Capability | Can |
|---|---|
| `manage_options` (Administrator) | Everything, including **Settings** (API URL, token, booking mode, sender) |
| `manage_woocommerce` (Shop Manager) | Shipments, Logs, Help, booking, refreshing tracking, CSV export, the order-screen box |
| Customers | Their own shipment info and tracking |

Settings are administrator-only because whoever can change the API base URL can redirect the access token.

## 6. What is stored

| Where | What |
|---|---|
| `{prefix}parcelflow_shipments` | One row per order: state, status, shipment/tracking numbers, label URL, carrier, fees, request snapshot, cached tracking events |
| `{prefix}parcelflow_logs` | API audit log (redacted), purged after the retention period (default 30 days) |
| Option `parcelflow_wc_settings` | All settings except the token |
| Option `parcelflow_wc_access_token` | The token, encrypted (libsodium), unless `PARCELFLOW_WC_ACCESS_TOKEN` is defined |
| Order meta `_parcelflow_*` | Shipment references mirrored onto the order |
| Shipping line meta `_parcelflow_*` | The rate chosen at checkout |
| Product meta | `_parcelflow_hs_code`, `_parcelflow_declared_value`, `_parcelflow_customs_description` |

Deleting the plugin keeps data unless **Delete all data on uninstall** is enabled. Order meta is never deleted.
