YEEDIPAY CUST0003 - BIGROCK CUSTOMER PORTAL
===========================================

WHAT IS INCLUDED
- Public customer payment page: /p/CUST0003
- Customer OTP login: /c/CUST0003
- Customer dashboard with Confirmed / Pending / Refunded / Settled / Ledger totals
- Merchant QR / payment-link QR
- Account statement + CSV/PDF exports
- Platform fees + GST view + CSV/PDF exports
- Support tickets + replies
- Wallet balance, recharge and withdrawal request
- Payment result + invoice PDF/print
- Bank SMS relay ingestion endpoint: /api/bank-sms
- Bank-SMS exact amount/account/UTR matching already present in BankSmsService
- Safe UTR handling: customer-entered UTR alone does NOT make a transaction SUCCESS
- CSRF protection, session login, duplicate UTR protection, protected storage

BIGROCK INSTALL - SHORT STEPS
1. Upload the ZIP contents to the document root of the CUSTOMER subdomain.
2. In BigRock/cPanel create/select the MySQL/MariaDB database and user.
3. Import database/schema.sql inside that selected database.
4. Edit .env:
   DB_HOST=localhost
   DB_NAME=<your BigRock DB name>
   DB_USER=<your BigRock DB user>
   DB_PASS=<your BigRock DB password>
   PAY_PUBLIC_URL=https://<your customer domain>
5. Keep APP_KEY secret. A random key is already generated in the packaged .env.
6. Ensure PHP 8.1+ extensions: pdo_mysql, openssl, fileinfo, mbstring/iconv.
7. Give PHP write permission to storage/logs and storage/utr_screenshots.
8. Open /health. It must show database=connected and status=ok.
9. Ensure customers row CUST0003 has the correct registered phone number.
10. Keep/import your REAL bank_profiles data. This package intentionally does NOT add a fake UPI/VPA.
11. Configure your Android bank SMS relay device/secret according to the existing BankSmsService flow.
12. For OTP SMS, set SMS_API_URL and SMS_API_TOKEN for your SMS provider. If blank, OTP is logged locally only and is NOT delivered to the phone.

IMPORTANT LIVE REQUIREMENTS
- Real BigRock database username/password are not known to this package, so .env has CHANGE_ME placeholders for DB_USER/DB_PASS.
- Real bank VPA/QR/webhook secret cannot be invented. Use the existing live bank_profiles from your current YEEDIPAY DB or insert your actual bank profile.
- OTP phone delivery needs your real SMS provider endpoint/token. The software flow is included; provider credentials are external.
- DEV_SHOW_OTP must remain 0 on production.

SECURITY / PAYMENT RULE
A customer-typed UTR is saved for reference, but SUCCESS requires authenticated bank SMS auto-match. This prevents dummy/wrong UTR from releasing service.

MAIN URLS
/health
/p/CUST0003
/p/CUST0003/qr
/c/CUST0003
/api/bank-sms

FILES ADDED TO COMPLETE THE CUSTOMER PACKAGE
app/OtpService.php
app/StatementService.php
app/BillingService.php
app/TicketService.php
app/WalletService.php
customer/views/dashboard.php
customer/views/merchant_qr.php
customer/views/permanent_qr.php
customer/views/statement.php
customer/views/billing.php
customer/views/tickets.php
customer/views/ticket_view.php
customer/views/wallet.php
customer/views/wallet_recharge.php
customer/views/wallet_recharge_pay.php
customer/views/wallet_withdraw.php
customer/views/login.php
customer/views/checkout.php

MERCHANT API + WEBHOOK PATCH
----------------------------
Professional merchant flow is included in this build:
Merchant Website -> POST /api/v1/payments -> Hosted /pay/{payment_id} -> Bank-confirmed SUCCESS -> signed webhook -> merchant return URL.

Merchant setup (CLI):
php tools/create_merchant.php CUST0004 "Merchant Name" "https://merchant.example/yeedipay-webhook" "https://merchant.example/payment-result" LIVE

Read:
docs/PATCH_INSTRUCTIONS_TELUGU.md
docs/MERCHANT_API_GUIDE.md
docs/CUSTOMER_DELIVERY_KIT.md

For webhook retries, configure cron to run tools/process_webhooks.php every 1-5 minutes.
Never share bank SMS relay secrets, bank credentials, database credentials, or .env with merchants.


30-MINUTE MULTI-QR ROTATION
---------------------------
The customer-facing permanent QR is the stable YEEDIPAY URL QR (/p/CUSTxxxx/qr).
Backend bank/UPI QR profiles may be unlimited; enabled profiles rotate in rotation_order,
30 minutes each. A payment locks the selected bank_id at creation, so an in-flight
transaction never changes bank when the global rotation advances.

Existing install: import database/qr_rotation_30min_patch.sql once.
CLI helper: php tools/qr_pool.php list | add | enable | disable | enable-auto
See docs/QR_ROTATION_30MIN_PATCH_TELUGU.md.

FINAL PRE-LIVE CHECK
--------------------
Run: php tools/preflight.php
Do not go live until it reports RESULT: READY and a real ₹1 end-to-end payment succeeds.
