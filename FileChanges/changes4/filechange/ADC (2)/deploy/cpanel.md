# cPanel — admin.yeedipay.in + customer.yeedipay.in

Both subdomains use the **same document root**:

`public_html/YeediPayment/portals`

That folder must contain the full project (`index.php`, `.htaccess`, `admin/`, `customer/`, `app/`, `.env`, `database/`).

## 1. Upload
Upload this package to `public_html/YeediPayment/portals`.

## 2. Subdomains
In cPanel → Domains / Subdomains:

| Host | Document root |
|---|---|
| `admin.yeedipay.in` | `public_html/YeediPayment/portals` |
| `customer.yeedipay.in` | `public_html/YeediPayment/portals` |
| `sms-demo.yeedipay.in` | `public_html/YeediPayment/portals` |

Enable SSL (AutoSSL) on both.

## 3. Live `.env`
On the server, set:

```
APP_ENV=production
APP_KEY=<same long secret as local, or a new 32+ char secret>
APP_TIMEZONE=Asia/Kolkata
ADMIN_PUBLIC_URL=https://admin.yeedipay.in
PAY_PUBLIC_URL=https://customer.yeedipay.in
SMS_DEMO_PUBLIC_URL=https://sms-demo.yeedipay.in
SESSION_COOKIE_SECURE=true

DB_HOST=localhost
DB_PORT=3306
DB_NAME=<cPanel MySQL database name>
DB_USER=<cPanel MySQL user>
DB_PASS=<cPanel MySQL password>
```

Create the MySQL database in cPanel, import `database/schema.sql` in phpMyAdmin (or upload a dump of your local `yeedipay` database if you want existing customers/banks).

Create the admin user over SSH/terminal:

```bash
php bin/create_admin.php admin 'YourLivePassword12+'
```

## 4. Cron
```cron
* * * * * /usr/bin/php /home/ACCOUNT/public_html/YeediPayment/portals/cron/worker.php >/dev/null 2>&1
```

Replace `ACCOUNT` with your cPanel username.

## 5. After go-live
1. Open `https://admin.yeedipay.in` → login
2. Confirm Bank QR is active
3. Open a customer payment link on `https://customer.yeedipay.in/p/CUST0001`
4. Confirm that link appears in Admin → Customers

Required PHP: 8.1+, PDO MySQL, OpenSSL, `mod_rewrite`.
