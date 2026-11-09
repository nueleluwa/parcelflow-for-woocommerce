# Developer reference

Namespace `ParcelFlow\WooCommerce`, PSR-4 under `src/`. Prefixes: PHP constants `PARCELFLOW_WC_*`, options, hooks
and jobs `parcelflow_wc_*`, meta `_parcelflow_*`.

> Only the items under **Public extension points** are meant to be relied on. Everything else is internal and may change before 1.0.

## Public extension points

### Filters

| Filter | Default | Purpose |
|---|---|---|
| `parcelflow_wc_declare_hpos_compatible` | `false` | Return `true` to declare HPOS (`custom_order_tables`) compatibility. Do this only after verifying HPOS on your store. |
| `parcelflow_wc_client_ip` | `REMOTE_ADDR` | Visitor identifier for the public-tracking throttle. Return the trusted client IP when behind a proxy/CDN. |
| `parcelflow_wc_allow_insecure_api_url` | `false` | Allow a non-HTTPS API base URL (local development only; the token is then sent in clear text). |
| `woocommerce_shipping_parcelflow_is_available` | enabled + configured | Standard WooCommerce method-availability filter for the `parcelflow` method. |

```php
// Behind Cloudflare: use the real client IP for the tracking throttle.
add_filter( 'parcelflow_wc_client_ip', function ( $ip ) {
	return isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) : $ip;
} );

// After testing HPOS on your store:
add_filter( 'parcelflow_wc_declare_hpos_compatible', '__return_true' );
```

### Constant (wp-config.php)

| Constant | Purpose |
|---|---|
| `PARCELFLOW_WC_ACCESS_TOKEN` | API token. When defined it overrides the stored token and the Settings field is hidden. |

### Shortcode
`[parcelflow_tracking]` renders the tracking form and timeline. The lookup uses the GET parameter `pf_track`
(e.g. `/track-shipment/?pf_track=PF-2026-89472`).

### My Account endpoint
`parcelflow-tracking` (menu label “Track Shipment”). If the URL 404s after activation, re-save Settings → Permalinks.

## Shipping method
- Method ID: `parcelflow` (class `Shipping\ShippingMethod`), supports shipping zones and instance settings.
- Rate IDs: `parcelflow:{instance_id}:{carrier_id}`.
- Rate meta (copied to the order's shipping item; underscore keys are hidden in admin):

| Meta key | Content |
|---|---|
| `_parcelflow_carrier_id` / `_carrier_name` / `_carrier_logo` | From the API |
| `_parcelflow_delivery_time` / `_additional_info` | From the API |
| `_parcelflow_selling_price` / `_pickup_fee` | Customer price and pickup fee |
| `_parcelflow_rate_id` | Only if the API returned one, otherwise empty |
| `_parcelflow_shipment_type` | Local / Interstate / International / Import used for the quote |
| `_parcelflow_request_hash` / `_quoted_at` | Hash of the quoted package/booking options; quote timestamp |

## Order meta (mirrored by the plugin)

`_parcelflow_shipment_id`, `_parcelflow_shipment_number`, `_parcelflow_tracking_number`, `_parcelflow_label_url`,
`_parcelflow_status`, `_parcelflow_total_fee`, `_parcelflow_carrier_id`, `_parcelflow_carrier_name`,
`_parcelflow_rate_id`, `_parcelflow_booking_state` (`awaiting|in_progress|booked|failed|uncertain`).

Read them with the CRUD API:

```php
$order = wc_get_order( $order_id );
$tracking = $order->get_meta( '_parcelflow_tracking_number' );
```

## Database tables

`{prefix}parcelflow_shipments`: one row per order (`UNIQUE order_id`), columns include `state`, `status_group`
(`pending|processing|in_transit|delivered|failed`; derived only from the four statuses the API documentation names plus the plugin's own booking state, for dashboard counts),
`shipment_number`, `tracking_number`, `label_url`, `status`, `carrier_*`, `rate_id`, `shipping_fee`, `total_fee`,
`request_json`, `events_json`, timestamps (UTC).

`{prefix}parcelflow_logs`: `event_type`, `success`, `order_id`, `shipment_number`, `endpoint`, `http_code`, `message`,
`context` (redacted JSON).

## Background jobs (Action Scheduler, group `parcelflow`)

| Hook | Schedule | Does |
|---|---|---|
| `parcelflow_wc_refresh_tracking` | every 30 min | Marks stale `in_progress` bookings `uncertain`; refreshes open shipments older than the configured interval |
| `parcelflow_wc_purge_logs` | daily | Deletes logs older than the retention period |
| `parcelflow_wc_book_order` | one-off, automatic mode only | Books an `awaiting` order ~30 s after payment |

## Admin actions (`admin-post.php`)
`parcelflow_wc_save_settings` (admin only), `_book`, `_refresh_tracking`, `_link_existing`, `_confirm_not_created`,
`_export_csv`, `_test_connection`, `_clear_logs`. All verify capability and a nonce; booking-related nonces are bound to the order ID.

## API client
`Api\Client` implements exactly the five documented endpoints under `engine/v1/apis/shipments/`:

| Method | Endpoint |
|---|---|
| `calculate_shipment()` | `POST calculate-shipment` |
| `create_shipment()` | `POST create-shipment` |
| `get_shipment_estimate()` | `POST get-shipment-estimate` (not used at checkout) |
| `get_shipment_history()` | `GET get-shipment-history` |
| `track_shipment()` | `GET track-shipment` |

Every call returns an `Api\Response` (`success`, `http_code`, `body`, `message`, `transport_error`, `not_sent`,
`malformed`) with `is_definitive_rejection()` / `is_safe_to_retry()` used by booking. Requests: HTTPS only, header named
by the *Authentication header name* setting (default `ACCESS_TOKEN`), no redirects, form-encoded nested arrays.
To support a new endpoint, add a method to `Client` that calls `request()`, and log through the same path.

## Adding or changing settings
Settings are schema-driven (`Settings::schema()`): add a field there (tab, type, default, label) and it is rendered,
defaulted and sanitised automatically. Types: `checkbox, select, text, textarea, url, email, int, float, country,
currency, header, token`.

## Coding standards & tests
See [CONTRIBUTING.md](../CONTRIBUTING.md) and [TESTING.md](TESTING.md).
