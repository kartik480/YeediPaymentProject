# YEEDIPAY Merchant API Patch — ఎక్కడ ప్యాచ్ చేయాలి

ఈ patch, `YEEDIPAY_CUST0003_BigRock_COMPLETE` package మీద **Payment Link + API + Webhook** add చేస్తుంది.

## సులువు ప్యాచ్ విధానం

1. **Backup తీసుకోండి** — present `public_html` folder + database export.
2. Patch ZIP extract చేయండి.
3. Patch ZIPలో ఉన్న folders/filesని మీ YEEDIPAY root (`public_html` లేదా YEEDIPAY install folder)లో copy చేయండి.
4. Existing file overwrite అడిగితే **Yes** చేయాలి — కానీ `.env` ని overwrite చేయకండి.
5. phpMyAdmin → మీ YEEDIPAY database → Import → `database/merchant_api_patch.sql` import చేయండి.
6. `.env`లో ఈ values add చేయండి:
   - `WEBHOOK_MAX_ATTEMPTS=6`
   - `WEBHOOK_ALLOW_HTTP=0`
7. Merchant create చేయడానికి cPanel Terminal/SSHలో:

```bash
php tools/create_merchant.php CUST0004 "Merchant Name" "https://merchant.com/yeedipay-webhook" "https://merchant.com/payment-result" LIVE
```

8. Outputలో వచ్చే `MERCHANT_ID`, `API_KEY`, `API_SECRET`, `WEBHOOK_SECRET` secureగా save చేయండి.
9. Merchant website నుంచి `POST /api/v1/payments` test చేయండి.
10. Responseలో వచ్చిన `payment_url` open చేసి ₹1 test payment చేయండి.
11. Bank SMS relay match అయిన తర్వాత status `SUCCESS` అవుతుందో చూడండి.
12. Merchant webhookకి `payment.success` event వచ్చిందో verify చేయండి.

## ఏ files కొత్తవి?

- `app/MerchantApiService.php` — API authentication, payment create/status, merchant order mapping.
- `app/WebhookService.php` — signed webhook queue + retry delivery.
- `database/merchant_api_patch.sql` — 3 కొత్త tables.
- `tools/create_merchant.php` — merchant credentials generate చేయడానికి CLI tool.
- `tools/process_webhooks.php` — pending webhooks retry worker.
- `docs/MERCHANT_API_GUIDE.md` — customer developer API guide.
- `docs/CUSTOMER_DELIVERY_KIT.md` — ప్రతి customerకి మనం ఇవ్వాల్సిన items.
- `samples/php/*` — copy-paste PHP samples.

## ఏ existing files modify అయ్యాయి?

- `app/bootstrap.php` — Merchant API/Webhook services load అవుతాయి.
- `customer/public/index.php` — `/api/v1/payments` create/status routes add అయ్యాయి.
- `app/ReconciliationService.php` — bank-confirmed final status వచ్చాక merchant webhook queue అవుతుంది.
- `app/BankSmsService.php` — SUCCESS తర్వాత pending webhook delivery ప్రయత్నిస్తుంది.
- `customer/views/checkout.php` — Order ID + Return to Merchant button.
- `database/schema.sql` — fresh installationకి merchant API tables కూడా కలిపాం.
- `.htaccess` — `tools/` మరియు `docs/` browser నుంచి block.
- `.env.example` — webhook settings.

## BigRock Cron (recommended)

Webhook మొదటి delivery వెంటనే try అవుతుంది. Network failure ఉంటే retry queue ఉంటుంది. BigRock Cronలో ప్రతి 1–5 minutesకి:

```bash
php /home/CPANEL_USER/public_html/tools/process_webhooks.php
```

మీ actual hosting pathకు మార్చండి.

## ముఖ్యమైన security rules

- Customerకి **Bank SMS relay secret / bank API secret / database password** ఇవ్వకండి.
- `API_SECRET` మరియు `WEBHOOK_SECRET` email/plain WhatsAppలో share చేయడం avoid చేయండి; secure channel వాడండి.
- Merchant requestలో user ఇచ్చిన UTR వల్ల SUCCESS చేయదు. **Authenticated bank confirmation మాత్రమే SUCCESS source**.
- Productionలో HTTPS తప్పనిసరి. `WEBHOOK_ALLOW_HTTP=0` ఉంచండి.
