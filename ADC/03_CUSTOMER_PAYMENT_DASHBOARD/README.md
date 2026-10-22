# YEEDI Customer Payment Dashboard — 30 Second Verification UX

## Flow
1. Customer scans / opens the UPI payment for the server-created order amount (example ₹500).
2. After the UPI app reports payment completed, customer taps **I HAVE PAID — CHECK NOW**.
3. The button calls `02_SERVER_CORE/api/payments/acknowledge.php`.
4. This does **not** create SUCCESS. It stores `customer_ack_at` once and starts a server-authoritative 30-second verification window.
5. The dashboard displays an animated verification panel and polls `status.php` every ~2 seconds.
6. If the Android SMS Relay uploads the verified matching bank CREDIT SMS during those 30 seconds, the next poll immediately becomes SUCCESS.
7. If the SMS is delayed beyond 30 seconds, the page changes to **WAITING FOR BANK SMS CONFIRMATION**, says **DO NOT PAY AGAIN**, and continues polling (~3 seconds).
8. Whenever the delayed verified SMS arrives within the configured matching/grace window, status becomes SUCCESS automatically.

## Important
- Refreshing the page does not lose the state; the acknowledgement time is stored on the server.
- Do not use the customer button itself as proof of payment.
- Delivery/service release must depend on `delivery_ready=true` / `status=SUCCESS` only.
- Set `config.js -> apiBase` to your real HTTPS Server Core URL.
