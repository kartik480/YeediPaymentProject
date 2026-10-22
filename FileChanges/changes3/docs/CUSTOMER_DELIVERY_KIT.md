# YEEDIPAY — Customer / Merchantకి ఇవ్వాల్సిన Complete Kit

ప్రతి Website/API customerకి ఈ items ఇవ్వండి:

1. **Customer ID** — ఉదా. `CUST0004`
2. **Merchant ID** — ఉదా. `MER0004`
3. **API Base URL** — ఉదా. `https://pay.yeedipay.in`
4. **Create Payment Endpoint** — `POST /api/v1/payments`
5. **Payment Status Endpoint** — `GET /api/v1/payments/{payment_id}`
6. **API Key** — merchant authentication.
7. **API Secret** — HMAC signature కోసం; one-time secure delivery.
8. **Webhook Secret** — YEEDIPAY webhook signature verify చేయడానికి.
9. **Webhook URL** — customer developer మీకు ఇవ్వాలి; మీరు merchant profileలో set చేయాలి.
10. **Return/Success URL** — payment తర్వాత customer తిరిగి వెళ్లే website page.
11. **Direct Payment Link** — basic integration కోసం `https://pay.yeedipay.in/p/CUST0004`.
12. **Dashboard Login Link** — `https://pay.yeedipay.in/c/CUST0004`.
13. **API Documentation** — `MERCHANT_API_GUIDE.md` లేదా PDF version.
14. **PHP/cURL Sample Code** — `samples/php/create_payment.php`.
15. **Webhook Verify Sample** — `samples/php/webhook_receiver.php`.
16. **Test Payment Procedure** — small live test or sandbox plan.
17. **Support Contact** — integration issue కోసం YEEDIPAY support details.

## Customer మనకు ఇవ్వాల్సినవి

- Website domain
- Webhook URL
- Return/Success URL
- Developer contact/email
- Integration mode: Link Only / API + Hosted Page

## Customerకి ఇవ్వకూడనివి

- Bank SMS relay device secret
- Bank webhook/API secret
- Database username/password
- `.env` file
- Other merchants' API keys/secrets
- Internal bank account mapping configuration

## Basic customerకి minimum

`Customer ID + Direct Payment Link + Dashboard Login`

## Professional website customerకి recommended

`Merchant ID + API Key + API Secret + Webhook Secret + API Guide + Sample Code + Payment/Status URLs + Dashboard Link`
