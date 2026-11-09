# Installation & configuration

## Requirements
* WordPress 6.4+ (WooCommerce 11.2.x itself requires WordPress 7.0+)
* WooCommerce 8.5+ (the supplied reference build is 11.2.2)
* PHP 7.4+ with `openssl`/`sodium` (bundled with modern PHP/WordPress) and outbound HTTPS to the ParcelFlow API

> **Tested versions:** none yet in a live WordPress. Only PHP 8.3 syntax linting and a stubbed unit test of the pure logic
> have been run. Record your tested versions in `docs/TESTING.md` after running the checklist.

## Install
1. WordPress Admin → Plugins → Add New → Upload Plugin → choose `parcelflow-for-woocommerce.zip` → Activate.
   (Or copy the `parcelflow-for-woocommerce` folder to `wp-content/plugins/`.)
2. Activation creates two tables (`{prefix}parcelflow_shipments`, `{prefix}parcelflow_logs`) and a **Track Shipment** page at `/track-shipment/` (only if that slug is free; otherwise add `[parcelflow_tracking]` to any page).
3. If permalinks 404 on My Account → Track Shipment, visit Settings → Permalinks and click Save.

## Configure (ParcelFlow → Settings)
**General** – enable ParcelFlow; API base URL (staging provided; production URL pending); paste the access token (stored encrypted; or define `PARCELFLOW_WC_ACCESS_TOKEN` in `wp-config.php`); leave *Enable checkout rates* on. Then ParcelFlow → Help / API Status → **Test connection**.

**Sender / Warehouse** – the *dispatch* address (all fields required by the API).

**Shipping & Booking** – **shipment type (required: choose Local, Interstate, International or Import; rates are not requested until you do)**, package type, **booking mode (leave on Manual)**, payment method (`Wallet`), station IDs, pickup/insurance (only with values from ParcelFlow), package dimension strategy (a **fixed carton** is recommended), packaging weight, fallback HS code/weight (optional), declared-value basis.

**Carriers / Services** – hide carriers (list fills from real rate responses); add service lines `id|label|amount`.

**Advanced** – timeout, header name, debug logging, tracking refresh hours, failure e-mail, log retention, delete-data-on-uninstall.

## Shipping zone
WooCommerce → Settings → Shipping → choose a zone → *Add shipping method* → **ParcelFlow**.

## Products
Product data → **Shipping** tab: weight, L×W×H (native), plus **HS tariff code**, optional **ParcelFlow declared value** and **customs description**.

## Daily use
* Customer pays → order appears in **ParcelFlow → Shipments** as *Pending* (awaiting booking).
* Open the order (side box “ParcelFlow Shipment”) or the shipment page → **Book ParcelFlow Shipment**.
* If a booking ends **uncertain**, follow *Reconcile* on the shipment page. Never rebook blindly.
* Customers see *Shipment Information* in My Account → Orders → View, and can use **Track Shipment**.

## Uninstall
Deactivating removes scheduled jobs. Deleting the plugin keeps data unless *Delete all data on uninstall* is on. Order meta (`_parcelflow_*`) is never deleted.
