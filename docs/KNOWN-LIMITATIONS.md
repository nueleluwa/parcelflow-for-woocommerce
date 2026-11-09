# Known limitations

**Not production-ready** until the blocking API questions (rate_id, production URL, auth header, wallet funding) are answered and the staging test plan has passed.

* **Unverified in a live store.** The WooCommerce folder supplied lacked its PHP sources, no WordPress runtime was available, and no staging token was supplied; only linting and stubbed logic tests were possible.
* **`rate_id`**: bookings need a `rate_id` the documented rate response does not include. Until ParcelFlow confirms it, bookings require manual entry of a ParcelFlow-supplied value.
* **HPOS**: code uses CRUD only, but compatibility is *not declared* (filter `parcelflow_wc_declare_hpos_compatible`, default false) until verified.
* **Checkout Blocks**: no block integration; rates come through the standard shipping-rate API as a text label (“Carrier (delivery time)”). Carrier logos and “additional info” are classic-checkout only. Not tested.
* **Single package** per order; no carton splitting or bin-packing; dimension estimate is a heuristic (use a fixed carton).
* **Single currency**: prices are interpreted in one configured currency; no conversion (rates are hidden on mismatch).
* **No cancel / re-book / label re-issue** (no documented endpoints). Shipments are not changed when a WooCommerce order is cancelled or refunded after booking.
* **Variable products**: variations inherit the parent’s HS code / declared value / description; there are no per-variation fields.
* **Tracking** is polled, not pushed (no webhooks documented). Status grouping on the dashboard is keyword-based on an open-ended status list.
* **Public tracking** is open to anyone holding a shipment/tracking number issued by this store (events only, throttled).
* **Estimate endpoint** (`get-shipment-estimate`) is implemented in the client but unused.
* Admin booking runs synchronously (bounded by the API timeout); automatic booking runs in Action Scheduler.
* Translations: strings are translatable (`parcelflow-for-woocommerce`); no `.pot` file generated yet.
