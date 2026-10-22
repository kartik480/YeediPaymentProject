# YEEDI Server Core — V5 30 Second Verification

Main API routes:
- `api/payments/create.php` — merchant backend creates order.
- `api/payments/acknowledge.php` — customer says payment was completed; starts verification buffer only.
- `api/payments/status.php` — customer/dashboard status polling.
- `api/relay/webhook_bank_sms.php` — Android relay sends signed bank CREDIT event.

Customer virtual states returned by `status.php`:
- `AWAITING_PAYMENT`
- `VERIFYING` (server-authoritative 30-second buffer after first acknowledgement)
- `WAITING_BANK_SMS` (30 seconds elapsed; not FAILED; continue polling)
- `CONFIRMED` (`status=SUCCESS`, `delivery_ready=true`)
- `REVIEW_REQUIRED`
- `EXPIRED`

Final success authority: verified bank CREDIT SMS only.

Production configuration:
1. Set DB environment variables.
2. Set exact sender IDs in `config.php`.
3. Add receiving accounts.
4. Provision Android relay device.
5. Provision merchant client.
6. Set dashboard API base URL.
7. Use HTTPS.
