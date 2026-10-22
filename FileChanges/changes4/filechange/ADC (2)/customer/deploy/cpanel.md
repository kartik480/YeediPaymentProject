# cPanel / Shared Hosting
1. Create a MySQL database + user and grant all privileges on that database.
2. Upload this package outside public_html when possible, e.g. `/home/ACCOUNT/yeedipay`.
3. Copy `.env.example` to `.env`; set DB credentials, APP_KEY, and public URLs.
4. Import `database/schema.sql` in phpMyAdmin (or run `php bin/install.php`).
5. Create the first admin: `php bin/create_admin.php admin 'StrongPassword12+'`.
6. Create subdomain `admin.yeedipay.in`, document root = `.../yeedipay/admin/public`.
7. Create subdomain `pay.yeedipay.in`, document root = `.../yeedipay/customer/public`.
8. Enable SSL for both subdomains.
9. Add cron every minute: `* * * * * /usr/bin/php /home/ACCOUNT/yeedipay/cron/worker.php >/dev/null 2>&1`
10. Login to Admin → upload bank QR(s) → configure SMS Gateway → create customer → copy its permanent payment link to your main website.

Required PHP: 8.1+, PDO MySQL, OpenSSL. cURL is recommended but the SMS adapter has a stream fallback.

Optional daily DB backup cron:
`15 2 * * * /bin/sh /home/ACCOUNT/yeedipay/bin/backup_mysql.sh >/dev/null 2>&1`
