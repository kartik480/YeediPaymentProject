# YEEDIPAY Merchant API v1

## Flow

Merchant Website → Create Payment API → YEEDIPAY Hosted Payment Page → Bank-confirmed payment → `SUCCESS` → Signed Webhook → Merchant order confirmation.

**Important:** Merchant/client reported UTR does not make a payment successful. Final success is driven by YEEDIPAY's authenticated bank confirmation flow.

## Authentication

Every API request requires these headers:

- `X-Merchant-Id`
- `X-Api-Key`
- `X-Timestamp` — Unix timestamp in seconds, maximum 5-minute clock skew.
- `X-Signature` — lowercase HMAC-SHA256.

Canonical text:

```text
{timestamp}\n{HTTP_METHOD}\n{route_path}\n{sha256(raw_body)}
```

Signature:

```text
hex(hmac_sha256(canonical_text, API_SECRET))
```

For GET requests, `raw_body` is empty.

## 1) Create Payment

`POST /api/v1/payments`

Example JSON:

```json
{
  "order_id": "ORD10025",
  "amount": "850.00",
  "customer_name": "Ramesh",
  "mobile": "9876543210",
  "email": "customer@example.com",
  "return_url": "https://merchant.example/payment-result"
}
```

Response:

```json
{
  "success": true,
  "merchant_id": "MER0004",
  "order_id": "ORD10025",
  "payment_id": "YDP...",
  "status": "PENDING",
  "amount": "850.00",
  "currency": "INR",
  "payment_url": "https://pay.yeedipay.in/pay/YDP...",
  "return_url": "https://merchant.example/payment-result",
  "utr": "",
  "created_at": "2026-10-10T00:00:00+00:00",
  "expires_at": "2026-10-10T00:01:00+00:00"
}
```

`order_id` is idempotent per merchant. If the same `order_id` is sent again, the existing payment is returned instead of creating a second merchant order.

Amount range in this package: INR 1.00 to INR 200,000.00.

## 2) Open Hosted Payment Page

Redirect/open the returned `payment_url` in the browser.

The page displays the YEEDIPAY QR/payment UI. Bank SMS/API confirmation remains the source of truth.

## 3) Check Payment Status

`GET /api/v1/payments/{payment_id}`

Use the same signature process, with an empty request body and route path exactly matching the endpoint.

Possible status values include:

- `PENDING`
- `SUCCESS`
- `FAILED`
- `EXPIRED`

On `SUCCESS`, `utr` contains the bank-confirmed reference if available.

## 4) Webhooks

Configured merchant webhook receives final events such as:

- `payment.success`
- `payment.failed`
- `payment.expired`

Headers:

- `X-Yeedipay-Event`
- `X-Yeedipay-Event-Id`
- `X-Yeedipay-Signature`

Verify signature as:

```text
hex(hmac_sha256(raw_webhook_json, WEBHOOK_SECRET))
```

Example payload:

```json
{
  "merchant_id": "MER0004",
  "order_id": "ORD10025",
  "payment_id": "YDP...",
  "amount": "850.00",
  "currency": "INR",
  "status": "SUCCESS",
  "utr": "326789123456",
  "paid_at": "2026-10-10T10:45:32+00:00",
  "return_url": "https://merchant.example/payment-result",
  "event_id": "evt_...",
  "event": "payment.success"
}
```

Merchant webhook must return an HTTP 2xx response after safely recording the event. Use `event_id` for webhook idempotency.

## 5) Return URL

After final payment status, the hosted page shows **Return to Merchant** when a return URL is configured.

Do not treat browser return alone as payment proof. The merchant server must rely on the signed webhook or signed Status API.

## Webhook Retry

Failed webhooks are queued and retried with backoff. For reliable retry processing, configure BigRock cron to run:

```bash
php /home/CPANEL_USER/public_html/tools/process_webhooks.php
```

## Security Checklist

- HTTPS only in production.
- Never expose API Secret or Webhook Secret in browser JavaScript.
- Generate payment from merchant backend/server, not from frontend JavaScript.
- Verify YEEDIPAY webhook signature before changing order status.
- Store processed `event_id` values so retries do not duplicate fulfillment.
- Never mark an order paid from a customer-supplied UTR alone.
