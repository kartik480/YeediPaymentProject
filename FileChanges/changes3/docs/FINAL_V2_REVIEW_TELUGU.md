# YEEDIPAY Final V2 — చివరి Production Review

ఈ V2లో final audit తర్వాత add చేసినవి:

1. **Payment QR window 5 minutes** — default `300 seconds`; `.env`లో `PAYMENT_QR_TTL_SECONDS=300`.
2. **Merchant order idempotency safety** — అదే `order_id`కి same amount అయితే existing payment return అవుతుంది; different amount అయితే reject అవుతుంది.
3. **Webhook SSRF hardening** — localhost/private/link-local/reserved IP callbacks block అవుతాయి; productionలో HTTPS public hostname మాత్రమే.
4. **Webhook double-send protection** — delivery ముందు row `SENDING`గా atomic claim అవుతుంది; cron/status polling raceలో duplicate send risk తగ్గుతుంది.
5. **Secure PHP session cookie** — HttpOnly + SameSite=Lax + HTTPSలో Secure cookie.
6. **Secret encryption fail-closed** — OpenSSL లేకపోతే కొత్త API/bank secrets insecure Base64గా save చేయదు.
7. **BigRock preflight checker** — upload తర్వాత `php tools/preflight.php` run చేసి PHP/extensions/.env/DB/QR rotation readiness చూడండి.
8. **HTTP response hardening** — nosniff, SAMEORIGIN, referrer, browser permission headers.

## Liveకి ముందు
- `.env`లో DB details + random 32+ char `APP_KEY` పెట్టాలి.
- `PAY_PUBLIC_URL` actual HTTPS domain పెట్టాలి.
- Real backend QR profiles మాత్రమే enable చేయాలి.
- 2+ QR profiles ఉంటే `php tools/qr_pool.php enable-auto` run చేయాలి.
- `php tools/preflight.php` చివర `RESULT: READY` రావాలి.
- ₹1 test payment చేసి Bank SMS → SUCCESS → webhook → merchant order confirm పూర్తి flow test చేయాలి.
