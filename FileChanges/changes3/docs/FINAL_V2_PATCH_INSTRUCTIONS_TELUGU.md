# YEEDIPAY FINAL V2 Patch — సులభమైన Installation

## Existing live siteకి
1. Website files + database backup తీసుకోండి.
2. `YEEDIPAY_FINAL_V2_HARDENING_PATCH.zip`ని project rootలో extract/overwrite చేయండి.
3. **`.env` patch ZIPలో ఉండదు**, కాబట్టి మీ live DB/API credentials overwrite కావు.
4. Existing `.env` చివర ఈ line add చేయండి:
   `PAYMENT_QR_TTL_SECONDS=300`
5. మీ `APP_KEY` కనీసం 32 characters strong random secretగా ఉందో చూడండి. `CHANGE_ME`/default value వాడకండి.
6. BigRock SSH/Terminal ఉంటే: `php tools/preflight.php` run చేయండి.
7. `RESULT: READY` వచ్చిన తర్వాత ₹1 end-to-end test payment చేయండి.
8. Testలో: permanent customer QR sameగా ఉండాలి → కొత్త payment current backend QRకి lock అవ్వాలి → Bank SMS match → SUCCESS → merchant webhook deliver కావాలి.

## Important
- QR rotation: 30 minutes per enabled backend QR.
- Payment QR validity: default 5 minutes (300 seconds).
- Same merchant `order_id`ని different amountతో reuse చేస్తే API reject చేస్తుంది.
- Webhook/return URLs public HTTPS domains మాత్రమే వాడండి; localhost/private IPs block అవుతాయి.
