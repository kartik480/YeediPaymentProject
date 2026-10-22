<<<<<<< HEAD
# YEEDIPAY — PHP + HTML + MySQL Final Package

This is the PHP/MySQL port of the final exact-bank-QR YEEDIPAY design.

**Production rule:** the bank-issued UPI QR payload is stored and opened exactly as decoded. YEEDIPAY does **not** inject `tr`, `tn`, `am`, customer code, or any other parameter into the bank URI. Customer/payment mapping remains inside MySQL.

## Operational inputs
After server + DNS + MySQL are configured by the developer, day-to-day go-live inputs are:
1. Bank QR(s) in Admin → Bank QRs.
2. SMS Gateway details in Admin → SMS Gateway.

Then create customers and copy their permanent `https://pay.yeedipay.in/p/CUSTxxxx` links into any website.

## Stack
- PHP 8.1+
- HTML/CSS/vanilla JavaScript
- MySQL 8.0+ or MariaDB 10.6+
- Apache or Nginx
- No Python runtime

## First install
```bash
cp .env.example .env
# edit DB credentials and APP_KEY
php bin/install.php
php bin/create_admin.php admin 'Use-A-Strong-Password-Here'
php bin/health_check.php
```

Admin and customer share the same `app/` layer, `.env`, and MySQL. Creating a customer in Admin immediately enables `/p/CUSTxxxx` on the customer site; payments and UTR reports show up in Admin → Reconciliation.

**Local (one server, both sides):**
```bash
cp .env.example .envac
# set DB_*, APP_KEY, and SESSION_COOKIE_SECURE=false for http://localhost
php bin/install.php
php bin/create_admin.php admin 'Use-A-Strong-Password-Here'
php -S localhost:8080 router.php
```
Run the server from this project folder (where `router.php` lives). If `/login` shows plain `Not Found`, stop other `php -S …:8080` processes and start again from here.

Then open `http://localhost:8080/admin/login`, `http://localhost:8080/login` (customer portal), and `http://localhost:8080/`.

**Production document roots:**
- `admin.yeedipay.in` → `admin/public`
- `pay.yeedipay.in` → `customer/public`

Add cron:
```cron
* * * * * /usr/bin/php /path/to/yeedipay/cron/worker.php >/dev/null 2>&1
15 2 * * * /bin/sh /path/to/yeedipay/bin/backup_mysql.sh >/dev/null 2>&1
```

## Bank QR upload
Admin browser decodes the image using the browser BarcodeDetector when available, with jsQR CDN fallback. The PHP server then validates and stores the exact `upi://pay?...` string. The original QR image is also archived under `storage/bank_qr/`.

If a browser cannot decode a particular QR, a fallback exact UPI URI field is available; payment runtime still remains PHP/MySQL.

## QR display
Customer permanent QR and desktop bank QR are rendered in the browser using qrcodejs. Direct mobile `upi://` payment does not depend on QR rendering.

## Important payment verification rule
Opening a UPI app is not proof of credit. Transactions remain PENDING until Admin/bank-record reconciliation (or an optional future bank webhook) confirms SUCCESS/FAILED. Unverified PENDING attempts become EXPIRED after the configured 7-day window.

See `docs/FUNCTION_MATRIX.md` and `deploy/cpanel.md`.
=======
# YeediPaymentProject
>>>>>>> 71f2737e3e0c0b5576f175f424e672ed6a6bc367
