# API questions for the ParcelFlow team

Each item below is **not defined by the supplied API documentation** (Shipments API v1, 16 pages) or
the implementation brief. For each one the plugin isolates the dependency and does *not* guess.
Please reply with the exact behaviour (and, where possible, a real sample response).

| # | Question | What the docs say | What the plugin does meanwhile | Where it lives |
|---|----------|-------------------|--------------------------------|----------------|
| 1 | **Where is `rate_id` returned?** | Create Shipment requires `booking.rate_id` ("Rate ID returned from calculation", example `RATE_DHL_101`). The Calculate Shipment Cost sample carrier object has *no* `rate_id`: only `carrier_id`, `carrier_name`, `delivery_time`, `weight`, `additional_info`, `selling_price`, `pickup_fee`, `carrier_cost_price`, `carrier_logo`. | Captures `rate_id` **only if** a carrier object literally contains a `rate_id` key; never derives it from `carrier_id` or anything else. If absent, booking is blocked with a clear message, and an administrator may type a rate ID supplied by ParcelFlow on the shipment page. | `Api/RateParser.php`, `Booking/BookingService.php` |
| 2 | **Production base URL** | Only staging (`parcelflow-admin-staging-…herokuapp.com`) is given. Sample logo URLs use `admin.parcelflow.io`. | Base URL and environment label are settings; a warning shows if "Production" is chosen with a staging URL. | Settings → General |
| 3 | **Authentication header** | "`ACCESS_TOKEN` passed in the HTTP header (received server-side as `HTTP_ACCESS_TOKEN`)". | Sends a header literally named `ACCESS_TOKEN`; the name is configurable. Note nginx drops underscore headers by default; would `Access-Token` also work? Is there a token prefix (e.g. `Bearer`)? What do invalid-token responses look like (status code/body)? | Settings → Advanced |
| 4 | **Wallet payment** | `payment_method` "must be Wallet for customers". | Sends `Wallet` (configurable). Open: how is a merchant wallet funded, is it debited at booking, what error appears with insufficient funds, and can booking follow an external (WooCommerce) payment? Keep booking **manual** until answered. | Settings → Shipping & Booking |
| 5 | **Shipment type rules** | Allowed: Local, Interstate, International, Import; no definitions. | Auto: different country → International; same country+state → Local; same country, different state → Interstate. "Import" is never auto-selected. A fixed type can be pinned in settings. | `Shipping/PayloadMapper::shipment_type()` |
| 6 | **Rate validity / expiry** | Not documented. | Short-lived cache (default 10 min, 0 disables), keyed on the exact payload. No expiry check at booking. Can a `rate_id` be reused at booking time hours/days later? | Settings → Shipping & Booking |
| 7 | **Webhooks** | None documented. | Polls `track-shipment` via Action Scheduler (default every 6 h per open shipment). | `Tracking/` |
| 8 | **Currency of `selling_price`** | Responses show `"selling_price": "64,803.24"` with no currency; booking has `currency` (example NGN). | Setting "Currency of ParcelFlow prices" (blank = store currency). If it differs from the store currency **no rates are shown** (no conversion). | Settings → General |
| 9 | **Does `selling_price` already include `pickup_fee`?** And how do `standard_fee`/`extras_fee` map? | Create Shipment: `shipping_fee` = standard + pickup + insurance + services; `extras_fee` = insurance_fee + pickup_fee. | `standard_fee` = `selling_price`; `extras_fee` = pickup_fee (only if pickup requested) + insurance fee; `services_fee` = configured services. | `PayloadMapper::create_payload()` |
| 10 | **Insurance & pickup catalogues** | `pickup_type_id`, `insurance_type`, `insurance_fee` and how fees are priced are not supplied. | Disabled by default; if enabled they use admin-entered values and block booking if incomplete. `image_url` is sent per item (required when insurance=Yes). | Settings → Shipping & Booking |
| 11 | **Service IDs** | `service_id` / `service_amount`; no catalogue endpoint. | Admin-configured list (`id\|label\|amount`). | Settings → Carriers/Services |
| 12 | **Carrier IDs** | Dynamic; IDs may be numeric or "X-Y". | Never hardcoded; learned from real rate responses and shown in Settings → Carriers (can be hidden). | `Shipping/RateService.php` |
| 13 | **Package limits** | `packages` is an array; no limits or packing rules. | The cart is sent as **one** package: weight = Σ(unit weight × qty) + packaging weight; dimensions = fixed carton or "max length, max width, stacked height" estimate. Is one carton per shipment expected? Maximum weight/size? | `Shipping/PackageBuilder.php` |
| 14 | **Declared value semantics** | "Declared value per unit"; currency must match across items. | Per-unit amount paid (ex-tax) or product price, in the store currency; per-product override. | Settings → Shipping & Booking |
| 15 | **Country / state format** | "Country name or code". Create Shipment just says "Country". | Sends the **country name** and **state name** (from WooCommerce lists). For countries with no WooCommerce state list (e.g. UK) the **city** is sent as `state`, because the field is required. Is that acceptable? | `PayloadMapper` |
| 16 | **`name`/`description` "letters and numbers only"** | Stated for sender/receiver name, item name and description. | Punctuation is stripped and replaced with spaces (accents kept). Are accented characters accepted? | `Support/Format::alnum()` |
| 17 | **Tracking key** | `track-shipment?shipment-number=`; "Unique tracking / shipment identifier". | Always sends the stored **shipment number**. Customers may type either number; the plugin resolves a tracking number to its shipment number locally. Does the endpoint also accept `tracking_number`? | `Tracking/TrackingService.php` |
| 18 | **Tracking response** | Sample has `"status": ""` and no carrier/tracking number. | Shows events as returned; never overwrites a known status with an empty one. What are the possible `status` values (for dashboard grouping)? | `Support/Status.php` |
| 19 | **Cancel / re-book / label re-issue** | No endpoints documented. | Not implemented ("Re-book/cancel if supported" in the UI guide cannot be built). | — |
| 20 | **History response** | No order reference field. | Used for reconciliation only (operator picks the matching shipment). | `Admin/ShipmentsPage.php` |
| 21 | **Get Shipment Estimate** | Optional quick estimate. | Client method exists; **not used at checkout** (the full calculation is required to obtain bookable rates). | `Api/Client.php` |
| 22 | **Idempotency** | Not documented. | Would a client-supplied idempotency key / order reference on Create Shipment be supported? It would make timeout reconciliation automatic. | — |

## Brief vs. documentation discrepancies noted

* `rate_id` (item 1): the main blocking discrepancy.
* The UI guide image uses **purple**; the brief mandates **green** — green was used.
* The UI guide shows "Re-book or cancel" and "Print label" — only label download is possible; no cancel/re-book endpoint exists.
* The UI guide's tracking page accepts a "tracking number"; the API is keyed by shipment number (item 17).
* The UI guide shows rate rows with carrier logos; carrier logos are only available when the API returns `carrier_logo`.
* The WooCommerce folder supplied in the project contains only `woocommerce.php`, `assets/`, `readme.txt`, `uninstall.php` (v11.2.2) — no `src/` or `includes/` — so shipping-method internals could not be inspected locally; only documented public APIs are used.
