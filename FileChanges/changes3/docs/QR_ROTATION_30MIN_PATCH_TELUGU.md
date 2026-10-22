# YEEDIPAY — 30 నిమిషాల Multi-QR Rotation Patch

## ఏమి చేస్తుంది
- Backendలో QR1, QR2, QR3… ఎన్ని enabled QR profiles ఉన్నా `rotation_order` ప్రకారం వాడుతుంది.
- ప్రతి enabled QRకి 30 నిమిషాల slot.
- ఉదాహరణ: QR1 00:00–00:29:59 → QR2 00:30–00:59:59 → QR3 01:00–01:29:59 → మళ్లీ QR1.
- Customerకి ఇచ్చే permanent QR మాత్రం `https://YOUR-DOMAIN/p/CUSTxxxx` URL QR. అది మారదు.
- Payment create అయిన క్షణంలో active backend QR transactionలో `bank_id`గా lock అవుతుంది. Rotation తరువాత మారినా ఆ pending transaction route మారదు.
- Bank SMS / reconciliation అదే locked `bank_id` ఆధారంగా match అవుతుంది.

## Patch చేయడం
1. Website files + database backup తీసుకోండి.
2. Patch ZIPలోని filesను project rootలో overwrite చేయండి.
3. phpMyAdmin → SQL / Import → `database/qr_rotation_30min_patch.sql` run చేయండి.
4. Backend QR profilesలో అవసరమైనన్ని QRలను enable చేసి `rotation_order` 1,2,3… ఇవ్వండి.
5. కనీసం 2 enabled profiles ఉన్నప్పుడు AUTO rotation enable అవుతుంది.
6. Customerకి ఇచ్చే QRని మార్చవద్దు. `/p/CUSTxxxx/qr` నుంచి permanent QRనే ఇవ్వండి.

## ముఖ్యమైన technical point
ఒక direct bank UPI QR imageకి destination మార్చి కూడా exact same QR imageగా ఉంచడం సాధ్యం కాదు. అందుకే customer-facing permanent QR YEEDIPAY URLను encode చేస్తుంది. ఆ URL ఎప్పుడూ అదే ఉంటుంది; backendలో payment route మాత్రమే rotate అవుతుంది.

## Test
- 3 QR profiles enable చేయండి.
- 30-minute boundariesలో కొత్త payment create చేసి transaction `bank_id` మారుతోందో చూడండి.
- ఇప్పటికే create చేసిన pending transaction `bank_id` మాత్రం మారకూడదు.
