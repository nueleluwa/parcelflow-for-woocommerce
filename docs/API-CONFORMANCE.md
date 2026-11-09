# API conformance

**Source of truth: *ParcelFlow Admin Shipments API Documentation, v1* (16 pages).** Nothing in this plugin may rely on an
endpoint, field, value or behaviour that the document does not state. Where the document is silent the plugin either
blocks the action, asks the administrator to supply the value, or labels the behaviour as its own and unconfirmed.
The implementation brief and the UI mock-up are *secondary*; where they disagree with the document, the document wins.

Page numbers refer to the document's own "Page n of 16" footers.

## 1. What the plugin sends

### Authentication (p. 2)
`ACCESS_TOKEN` request header; POST bodies are `application/x-www-form-urlencoded` (nested arrays are sent as bracketed keys, e.g. `package[packages][0][package_weight]`).
The header **name** is configurable because the document does not say whether proxies will pass an underscore header.

### `POST calculate-shipment` (pp. 3-6)

| Group | Fields sent | Doc |
|---|---|---|
| `sender` | `name, address_1, address_2, city, state, country, postcode, phone_no, email` | p. 4 |
| `receiver` | same as sender | p. 4 |
| `package` | `package_type` (`Parcel`/`Document`), `packages[]` | p. 4-5 |
| `package.packages[]` | `number_of_items, package_weight, package_length, package_width, package_height, package_items[]` | p. 5 |
| `package_items[]` | `name, description, quantity, value, value_currency, weight, hs_code` | p. 5 |
| `booking` | `shipment_type`, `shipping_date` (`Now`), optional `processing_station_id`, `insurance[request_insurance]`, `pickup[request_pickup, pickup_type_id]`, `services[][service_id]` | p. 5 |

Not sent: `package[package_image_url]` (optional).
Response fields read: `data.carriers[]` → `carrier_id, carrier_name, carrier_logo, delivery_time, weight, additional_info, selling_price, pickup_fee`; `success`, `message`.
`carrier_cost_price` is discarded. `rate_id` is read **only** if a carrier object literally contains it (the documented sample does not).

### `POST create-shipment` (pp. 7-10)

| Group | Fields sent | Doc |
|---|---|---|
| `sender`, `receiver`, `package` | as above, plus per item `image_url` (required when insurance = Yes) | p. 8-9 |
| `booking` | `shipment_type, carrier_id, rate_id, shipping_date, currency, payment_method`, optional `processing_station_id, service_station_id` | p. 9 |
| `booking.insurance` | `request_insurance, insurance_type, insurance_fee` (only when enabled) | p. 9 |
| `booking.pickup` | `request_pickup, pickup_type_id, pickup_fee` (only when enabled) | p. 9 |
| `booking.services[]` | `service_id, service_amount` | p. 9-10 |
| `booking.fees` | `standard_fee, services_fee, extras_fee, shipping_fee` | p. 10 |

Response fields read: `data.shipment_id, shipment_number, tracking_number, label_url, status, total_fee`.

### `GET get-shipment-history` (pp. 13-14)
Query: `start-date, end-date` (Y-m-d), `status`, `limit`, `offset`. Response record fields shown: `id, shipment_number, sender_name, receiver_name, carrier_name, status, created_at`.

### `GET track-shipment` (pp. 15-16)
Query: `shipment-number`. Response fields read: `data.status`, `data.events[]` (`id, timestamp, description`). `sender_address` / `receiver_address` are ignored and never displayed.

### `POST get-shipment-estimate` (pp. 11-12)
Implemented in the client, **not used** by the plugin.

## 2. Places the document is silent: what the plugin does instead

| Topic | Behaviour |
|---|---|
| **Where `rate_id` comes from** | Not stated. Never derived or guessed. Used only if the API returns a `rate_id` key; otherwise booking is blocked unless an administrator enters a value supplied by ParcelFlow. |
| **How to choose `shipment_type`** | Not stated. The administrator **must choose** one of the four documented values in Settings; until then no rates are requested and booking is blocked. An *experimental* auto-detect option exists, labelled as not defined by the documentation (off by default). |
| **Package dimensions** | `package_length/width/height` are required but no derivation from a cart is documented. The administrator enters a **fixed carton** (default); a product-based estimate is available but labelled experimental. Missing dimensions block rates. |
| **`state` for countries without states** | The document makes `state` required but says nothing more. WooCommerce collects none for such countries (e.g. the UK), so rates are **blocked** with "state is missing"; the plugin does not invent a value. |
| **Status values** | Only *Created*, *Pending*, *In Transit*, *Delivered* are named. Those four are recognised for dashboard grouping; every other value is shown verbatim and counted under *Processing*. Only *Delivered* is treated as final. |
| **Currency of `selling_price`** | Not stated. Configurable; rates are hidden (no conversion) if it differs from the store currency. |
| **How `selling_price`/`pickup_fee` map to `standard_fee`/`extras_fee`** | Not stated. `standard_fee = selling_price`; `extras_fee = pickup_fee (if pickup enabled) + insurance_fee`; `services_fee` = configured services. Open question. |
| **Rate expiry, webhooks, cancel / re-book / label re-issue, idempotency** | Not documented; not implemented (tracking is polled). |
| **Payment** | `payment_method` is sent as `Wallet` (configurable) because the document says it must be Wallet for customers; how the wallet is funded is not stated. |
| **Insurance / pickup / service catalogues** | Not provided; values are entered by the administrator, never defaulted. |
| **Missing item data** | `weight`, `hs_code`, dimensions are required by the document. Missing values block rates; they are only filled from fallbacks the administrator explicitly configures. |

## 3. Plugin behaviours that use the API in ways the document does not describe

These are conveniences built on documented endpoints, clearly labelled in the UI:

| Behaviour | Uses | Why it's still safe |
|---|---|---|
| **Connection test** | `GET get-shipment-history` for today, `limit=1` | Read-only; the document has no ping endpoint. |
| **Reconcile an uncertain booking** | `GET get-shipment-history` ±1 day | Read-only; an administrator picks the match. |
| **Tracking by tracking number** | Resolved locally to the stored shipment number, then `track-shipment?shipment-number=` | The documented parameter is "Unique tracking / shipment identifier"; the plugin only ever sends a shipment number it stored. |
| **Duplicate `length/width/height` on a package at booking** | Present in the document's Create Shipment *example* (p. 7) but not in its field table (p. 8) | Sent because the example shows them; confirm with ParcelFlow. |

## 4. Keeping this honest
- Any change to a payload or response parser must cite the document page in the PR.
- New assumptions go in [API-QUESTIONS.md](API-QUESTIONS.md) and, if they affect behaviour, in section 2 above.
- `tests/Unit/` asserts the key rules (no fabricated `rate_id`, `carrier_cost_price` dropped, undocumented statuses not interpreted, uncertain outcomes never retried).
