# Developer Guide — PHP/MySQL

## End-to-end flow
1. Admin uploads a bank merchant QR. Browser decodes it and PHP validates `upi://pay`, `pa`, `pn`, `cu`.
2. The exact payload is stored. No bank parameter is altered.
3. Admin manually activates a bank or enables automatic rotation. Rotation uses each enabled bank's `rotation_order` and `rotation_minutes`.
4. Admin creates customer `CUSTxxxx` and optional limits.
5. Website uses the permanent link `https://pay.yeedipay.in/p/CUSTxxxx`.
6. Customer starts payment. Server checks Customer ID limits and creates an internal PENDING attempt mapped to the active bank.
7. Checkout opens the exact stored bank UPI URI. The internal YEEDIPAY reference is NOT sent to the bank.
8. Customer can submit the UTR/RRN shown by the UPI app.
9. Admin reconciles manually or imports the bank CSV. Matching supports customer UTR/RRN, bank RRN, UPI Txn ID, or internal reference when available.
10. Final status queues an SMS. Worker retries SMS and expires unverified PENDING attempts after 7 days.

## Customer limits
Limits are application-side controls. Because the exact bank QR is not modified with `am=`, they cannot force the amount inside the UPI app. If a real reconciled SUCCESS amount exceeds a configured customer limit, the credit is preserved and marked `LIMIT REVIEW` instead of being discarded.

## MySQL
Import `database/schema.sql`. All server timestamps are stored in UTC. Day/week/month limits and reports use `APP_TIMEZONE` (default `Asia/Kolkata`) then convert bounds to UTC.

## SMS
The generic adapter supports JSON, form-urlencoded, or query-string payloads; a configurable auth header/token; sender ID; DLT template IDs; and editable message templates. Token is AES-256-GCM encrypted with `APP_KEY`.

## Security
- PDO prepared statements
- CSRF on POST routes
- `password_hash`/`password_verify`
- HttpOnly/SameSite sessions; Secure configurable
- OTP resend 45s, lifetime 5m, max 5 attempts
- UTR uniqueness
- Audit logs
- Admin password change
- Raw webhook data is stored only in Admin-side transaction data

## Webhook (optional)
The current manual 7-day workflow does not require a bank API. If a bank later provides a callback, the Admin service exposes `/api/banks/{BANKCODE}/webhook`, authenticated with the per-bank `X-Bank-Secret`.
