# Technical assessment & implementation plan

## 1. Inventory of supplied materials

| Item | Contribution |
|---|---|
| `ParcelFlow_WooCommerce_Claude_Implementation_Brief.docx` | Primary functional spec: scope, architecture, mapping tables (WooCommerce → API), booking workflow, security, acceptance criteria, test plan, open API questions. |
| `ParcelFlow_Shipments_API_Documentation_Update.pdf` (16 pp.) | Authoritative API reference: five endpoints, `ACCESS_TOKEN` header, form-encoded POSTs, sample payloads/responses. |
| `ShopEase × ParcelFlow WooCommerce UI Guide.png` | Visual/functional reference for 6 screens + journey. Uses purple; brief mandates green. Carrier names/prices are illustrative. |
| `woocommerce/` | WooCommerce **11.2.2** (requires WP ≥ 7.0, PHP ≥ 7.4) — **partial**: only `woocommerce.php`, `assets/`, `readme.txt`, `uninstall.php`. Used as a version reference only; not modified. |
| Tooling | PHP 8.3 CLI (curl, mbstring, openssl, sodium) → used for linting and a stubbed unit test. No WordPress/MySQL runtime was available. |

## 2. WooCommerce architecture relevant to the integration (public APIs only)

* **Shipping method** – subclass `WC_Shipping_Method`, register through `woocommerce_shipping_methods`, `calculate_shipping( $package )` + `add_rate()`; zone-based with instance settings. Rate `meta_data` is copied to the order's shipping line item, which is how the selected carrier/rate context is retained.
* **Rate caching** – WooCommerce caches rates per *package hash* in the session; `woocommerce_cart_shipping_packages` is used to add a contact-completeness flag so rates refresh when name/phone/email become valid.
* **Orders** – CRUD only (`wc_get_order`, `WC_Order::update_meta_data()/save_meta_data()`, `wc_get_page_screen_id('shop-order')` for HPOS-safe meta boxes).
* **Payment** – `woocommerce_order_status_processing|completed` (paid statuses) mark eligibility; `$order->is_paid()` guards booking.
* **Account** – `woocommerce_order_details_after_order_table` and `woocommerce_account_menu_items` + endpoint.
* **Background** – Action Scheduler (bundled with WooCommerce).
* **Checkout Blocks** – shipping methods are consumed through the Store API; rates appear as text labels. No custom block is shipped.

## 3. Plugin structure

```
parcelflow-for-woocommerce/
├─ parcelflow-for-woocommerce.php   bootstrap (headers, constants, autoload, hooks)
├─ uninstall.php
├─ src/  (namespace ParcelFlow\WooCommerce)
│  ├─ Plugin, Activator, Compatibility, Autoloader
│  ├─ Api/        Client (5 endpoints), Response, RateParser (rate_id adapter)
│  ├─ Settings/   Settings (schema-driven), Crypto (libsodium)
│  ├─ Shipping/   ShippingMethod, ShippingRegistrar, RateService, PackageBuilder, PayloadMapper
│  ├─ Products/   ProductFields (HS code, declared value, customs description)
│  ├─ Booking/    BookingService (lock, classification, reconcile)
│  ├─ Orders/     OrderIntegration (paid hooks, meta box, customer section)
│  ├─ Tracking/   TrackingService, TrackingPage (shortcode/endpoint), Scheduler (Action Scheduler)
│  ├─ Data/       Schema, ShipmentRepository, LogRepository
│  ├─ Support/    Logger, Redactor, Status, Format
│  └─ Admin/      Admin (menu), Actions (admin-post), pages, View
├─ admin/views/   settings, shipments, shipment-detail, logs, help, order-shipment-panel
├─ frontend/views/ tracking, order-shipment-info
├─ assets/        css (admin/frontend), js (admin)
└─ docs/
```

## 4. Key design decisions

1. **Own tables** for the shipment index (UNIQUE `order_id` = atomic duplicate guard) and logs (filterable audit); shipment references are also mirrored to **order meta** via CRUD.
2. **One carton per order**; dimensions are never summed blindly (fixed carton or documented estimate); missing weight / dimensions / HS code ⇒ no rates (unless the admin configured an explicit fallback). No invented values.
3. **No fabricated prices**: API failure, validation failure or currency mismatch ⇒ a clear message and *no* rate.
4. **Booking safety**: validate → atomic lock → send once → classify. Only a definitive 4xx rejection (or a request that never left) is retryable; timeouts, 5xx, malformed bodies and "success without reference" become **uncertain** and require operator reconciliation (history lookup + link, or explicit "nothing was created").
5. **Manual booking by default**; automatic mode is implemented through Action Scheduler but discouraged until `rate_id` and wallet behaviour are confirmed.
6. **Token**: encrypted at rest (libsodium, key from site salts) or defined as a `wp-config.php` constant; never rendered, never logged (redactor), never sent to the browser.
7. **Public tracking** only resolves shipments known to this store, shows events/status only (never addresses), validates input, throttles per IP, and caches briefly.

## 5. Roadmap / status

| Area | Status |
|---|---|
| Bootstrap, dependency checks, activation, tables | Done |
| API client for all 5 documented endpoints | Done (estimate endpoint unused at checkout by design) |
| Settings (5 tabs), encrypted token | Done |
| Shipping method, rates, caching, classic checkout UI | Done (untested in live WooCommerce) |
| Block checkout | Rates via Store API; labels only; **unverified** |
| Product HS code / declared value / description | Done (simple products; variations inherit parent) |
| Booking + duplicate protection + reconcile | Done (unit-tested classification; DB lock untested on MySQL) |
| Order meta box, My Account section/tab, tracking page | Done (untested in live WooCommerce) |
| Shipments dashboard, detail, CSV, logs, help, connection test | Done (untested in live WooCommerce) |
| Tracking polling / log purge via Action Scheduler | Done |
| **Blocked on ParcelFlow** | `rate_id` source, production URL, auth header semantics, wallet funding, shipment-type rules, rate expiry (see API-QUESTIONS.md) |
