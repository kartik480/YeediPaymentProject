# YEEDI SMS AUTO V5 — 30 SECOND FINAL FLOW

ఈ packageలో 3 parts విడిగా ఉన్నాయి. కానీ ఒకే `payment_id` / Server API ద్వారా communicate అవుతాయి.

## 1) Android SMS Relay App
Dedicated bank-SIM Android phoneలో bank CREDIT SMS వచ్చినప్పుడు amount, UTR/RRN, bank, account last4, sender ID, SMS received time మాత్రమే HMAC signatureతో Server Coreకి upload చేస్తుంది. Raw SMS/OTP upload చేయదు.

## 2) Server Core
- YEEDI backend ₹500 వంటి payment order ముందుగా create చేస్తుంది.
- Server Payment ID + token + bank/account/QR details ఇస్తుంది.
- Customer **I HAVE PAID** నొక్కితే `customer_ack_at` save అవుతుంది.
- Customer click వల్ల SUCCESS కాదు.
- 30 seconds వరకు virtual state `VERIFYING`.
- 30 secలో bank SMS match అయితే వెంటనే `SUCCESS`.
- 30 secలో SMS రాకపోతే `WAITING_BANK_SMS`; FAILED కాదు.
- Customer page polling కొనసాగుతుంది. Delayed SMS వచ్చిన వెంటనే SUCCESS కావచ్చు.
- Final success rule: verified bank CREDIT SMS + bank + receiving account + exact amount + valid time window = exactly one order.

## 3) Customer Payment Dashboard
Customer QR/UPI ద్వారా pay చేసిన తర్వాత **I HAVE PAID — CHECK NOW** నొక్కుతాడు. వెంటనే animated 30-second verification card వస్తుంది:

`Payment submitted → Verifying with bank → 30…29…28… → SUCCESS`

SMS delay అయితే:

`Bank confirmation delayed → Still checking → DO NOT REFRESH / DO NOT PAY AGAIN`

Page 2–3 secondsకొకసారి Server status check చేస్తూనే ఉంటుంది. Server SUCCESS ఇచ్చినప్పుడు **Payment Successful** + UTR చూపిస్తుంది; `delivery_ready=true` అవుతుంది.

## ₹500 exact flow
`Order ₹500 created on Server`
→ `Customer scans QR and pays ₹500`
→ `Customer taps I HAVE PAID`
→ `Server starts 30-sec verification buffer`
→ `Bank credit SMS reaches SIM`
→ `Android Relay sends signed ₹500 event to Server`
→ `Server matches Order ₹500 ↔ Bank SMS ₹500`
→ `SUCCESS`
→ `Customer Dashboard shows success`
→ `Delivery / service can release`

## Safety rule
ఒకే receiving accountలో same ₹500కి overlapping active orders ఉంటే SMSలో YEEDI Order ID ఉండదు. అందుకే V5 collision guard deliberately second same-amount slotను block చేస్తుంది. ఇది wrong customerకి success ఇవ్వకుండా ఉండటానికి. High concurrency అవసరమైతే multiple receiving accounts లేదా unique payable amount/strong bank-side transaction reference integration అవసరం.

## Existing V5 DB
Database backup తీసుకుని `02_SERVER_CORE/sql/upgrade_v5_to_core_developed.sql` run చేయాలి. Fresh install అయితే `02_SERVER_CORE/sql/install.sql`.

## Production before live
- HTTPS compulsory
- Real DB environment variables
- Exact ICICI / IndusInd / RBL sender IDs in server config
- Receiving account last4 + UPI details
- Device provision + Merchant provision
- Real ₹1 / ₹10 test, తర్వాత ₹500 UAT

**Final authority:** customer button కాదు; verified bank SMS match మాత్రమే SUCCESS.
