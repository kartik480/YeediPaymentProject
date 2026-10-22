# Razorpay Integration (Orders + Checkout + Verified Webhook)

This integration adds online **card / UPI / netbanking** collection via Razorpay to
YEEDIPAY, **without touching** the existing bank-QR, bank-SMS relay, settlement, or
reconciliation logic. Razorpay payments live in their own isolated ledger
(`razorpay_orders`, `razorpay_events`) and are confirmed **only** by trusted
server-side evidence.

## Why NOT the hosted "Payment Button" in the reference image

Razorpay's hosted Payment Button (the `payment-button.js` embed) generates its own
payment at Razorpay and gives you **no reliable way to tie a captured payment back to
a specific local payment attempt**. That breaks server-side reconciliation and the
"no double-credit" guarantee. We therefore implemented the **Orders API + Checkout.js
+ signed webhook** flow instead, which is the only approach that satisfies the
reconciliation requirements.

## Architecture

1. Customer enters an amount → server calls Razorpay **Orders API**, stores a local
   `razorpay_orders` row (`status=CREATED`) with our `local_reference` in the order
   `notes`/`receipt`.
2. **Checkout.js** opens with that `order_id`. The customer pays on Razorpay — we
   never see card numbers, CVV, or UPI PIN.
3. The browser callback is sent to `/razorpay/verify`, which checks the checkout
   signature **and then re-fetches the order from Razorpay with our secret key**
   (trusted). The browser alone never marks a payment PAID.
4. The **webhook** `/api/razorpay/webhook` (HMAC-SHA256 verified against the webhook
   secret) is the primary trusted confirmation. It is idempotent (dedupe on event id)
   and validates order association, amount, and currency before confirming.
5. Amount/currency mismatches → `REVIEW` (never auto-credited). Duplicate webhooks →
   ignored. Invalid signatures → `401` and recorded for audit.

## Files

- `database/migrate_razorpay.sql` — new tables (also auto-created by `ensureSchema`).
- `app/RazorpayService.php` — all logic (settings, orders, signatures, capture, webhook, reconciliation).
- `admin/views/payment_gateway.php` — admin settings (`/admin/payment-gateway`).
- `admin/views/razorpay_txns.php` — transactions + reconciliation (`/admin/razorpay`).
- `customer/views/razorpay_pay.php`, `razorpay_result.php` — customer pay + result.
- Routes wired in `admin/public/index.php`, `customer/public/index.php`, `customer/public/portal_routes.php`.
- `tests/razorpay_test.php` — automated tests.

## Database migration

```
mysql -u <user> -p <db> < database/migrate_razorpay.sql
```

The tables are also created automatically on first use via
`RazorpayService::ensureSchema()`, so no downtime is required. The migration is
idempotent (`CREATE TABLE IF NOT EXISTS`, `INSERT IGNORE`).

## Razorpay Dashboard configuration

1. **API Keys**: Dashboard → Settings → API Keys → generate. You get a Key ID
   (`rzp_test_…` / `rzp_live_…`) and a Key Secret (shown once).
2. **Webhook**: Dashboard → Settings → Webhooks → Add New Webhook.
   - URL: `https://customer.yeedipay.in/api/razorpay/webhook`
   - Secret: choose a strong random string (store it in the admin screen too).
   - Active events: `payment.captured`, `payment.failed`, `order.paid`.
3. In YEEDIPAY admin → **Payment Gateway**: enter Key ID, Key Secret, Webhook Secret,
   choose Test/Live, set min/max, tick "webhook configured", and Save. Secrets are
   stored **encrypted** (AES-256-GCM via `APP_KEY`); they are never shown again and
   never sent to the browser.

> Live mode is deliberately blocked until a webhook secret is stored and confirmed,
> so no live payment can be accepted without server-side verification.

## Tests

```
php tests/razorpay_test.php
```

Pure signature/parsing tests run anywhere. The integration block (idempotent capture,
amount-mismatch→REVIEW, webhook dedupe, bad-signature rejection) runs automatically
when a MySQL connection is available; it self-cleans its `TEST` rows.

Use Razorpay **Test Mode** keys and the dashboard's test cards/UPI to exercise the
full live-like flow. Simulated events do **not** prove a successful production
integration — run at least one real test-mode payment end to end, and verify the
webhook is delivered (Dashboard → Webhooks → recent deliveries).

## Rollback

The integration is additive and isolated. To disable or remove:

- **Disable instantly**: admin → Payment Gateway → untick *Enabled* → Save. Customers
  can no longer start Razorpay payments; existing data is untouched.
- **Hide the UI**: remove the two nav links (`Payment Gateway`, `Razorpay Txns`) in
  `admin/views/_head.php` and the `Card / Netbanking` link in
  `customer/views/_dash_head.php`.
- **Full removal**: revert the route additions in `admin/public/index.php`,
  `customer/public/index.php`, `customer/public/portal_routes.php`, the
  `require_once .../RazorpayService.php` line in `app/bootstrap.php`, and delete the
  new files. Then (optionally):
  ```
  DROP TABLE razorpay_events; DROP TABLE razorpay_orders; DROP TABLE razorpay_settings;
  ```
  No existing table is modified, so dropping these cannot affect transactions,
  settlements, or bank-SMS data.

## Backup before enabling

```
mysqldump -u <user> -p <db> > backup_before_razorpay_$(date +%F).sql
cp .env .env.backup
```

## Requirement checklist

| # | Requirement | Status |
|---|-------------|--------|
| A | Razorpay Dashboard configuration instructions | ✅ documented above |
| B | Admin Payment Gateway settings (encrypted secrets, enable/disable, webhook status, validation) | ✅ `/admin/payment-gateway` |
| C | Customer payment page (branding, authenticated customer, real amount control, Pay Now, processing/error states, no PIN/CVV collected) | ✅ `/c/{code}/razorpay` |
| D | PENDING attempt → checkout → processing page → **server-verified** evidence → status update only after validation → branded result → history | ✅ Orders+webhook+API fetch; browser never final |
| E | Admin transactions + search + filter + event log + reconciliation (no double-credit) | ✅ `/admin/razorpay` |
| F1 | Custom amount entry | ✅ real amount drives the order |
| F2 | Valid payment | ⏳ **needs test-mode run on a server with DB + live webhook** |
| F3 | Failed payment | ⏳ needs test-mode run |
| F4 | Cancelled checkout | ✅ ondismiss → result page shows real status; ⏳ confirm on server |
| F5 | Delayed server-side confirmation | ✅ result page polls `refreshFromApi`; ⏳ confirm on server |
| F6 | Browser closed before result | ✅ webhook confirms independently; ⏳ confirm on server |
| F7 | Result page refresh/reopen | ✅ idempotent; ⏳ confirm on server |
| F8 | Duplicate webhook delivery | ✅ unit-tested idempotent (event dedupe) |
| F9 | Invalid webhook signature | ✅ unit-tested → 401, recorded |
| F10 | Amount/currency mismatch | ✅ unit-tested → REVIEW |
| F11 | Unmatched provider payment | ✅ unit-tested → IGNORED |
| F12 | Unauthorized admin access | ✅ `Auth::requireAdmin()` guards all admin routes |
| F13 | Existing bank QR flow | ✅ untouched (separate ledger) |
| F14 | Existing Android bank-SMS relay | ✅ untouched |
| F15 | Existing settlement/reconciliation | ✅ untouched |

### Blocked until run on the live environment

- **F2–F7 end-to-end**: require a host with a reachable MySQL DB and a public HTTPS
  URL that Razorpay can call. They cannot be executed from this workstation (DB
  refused connection; no public webhook endpoint). Run `tests/razorpay_test.php` on
  the server to light up the integration block, then do one test-mode payment.
- **Do not enable Live mode** until a test-mode payment has been confirmed end to end
  and a real webhook delivery is visible in the event log.
