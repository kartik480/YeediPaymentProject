# FINAL QA — CURRENT ADC BANK SMS MATCH FLOW

Version: **SMS_MATCH_CURRENT_FLOW_FINAL_2026-09-30**

## Locked rule

Customer amount → QR → payment → I HAVE PAID → 30-second verification → authenticated Android Relay bank UPI CREDIT SMS → exact receiving account last-4 + exact amount + mandatory valid unique bank UTR/RRN → one eligible acknowledged pending transaction → SUCCESS → UTR display → service release.

## Code safeguards verified

- `I HAVE PAID` alone cannot set `SUCCESS`.
- Bank UTR/RRN is mandatory for automatic confirmation.
- Receiving account last-4 is mandatory.
- Exact credited amount is mandatory.
- Transaction must be acknowledged and still `PENDING`.
- Duplicate/reused UTR is rejected.
- Ambiguous same-amount candidates do not auto-confirm.
- Android relay requires HTTPS + Device ID + timestamp + nonce + HMAC signature.
- Replayed nonce is rejected.
- Stale/future SMS event protections remain enabled.
- Collection `SUCCESS` source is restricted to exact source `BANK_SMS_AUTO`.
- Provider bank callback route cannot confirm collections.
- Provider status refresh cannot alter collection status.
- Manual SMS-to-payment attachment is disabled.
- Customer UTR entry is not required or used in this flow.

## Static verification performed

- PHP syntax: **127/127 PASS**
- Java parser compilation: **PASS**
- Valid UPI credit with amount + account last-4 + UTR: **PASS**
- UPI credit without UTR: **REJECTED — PASS**
- UPI credit without account last-4: **REJECTED — PASS**
- Alternate collection SUCCESS source scan: only `BankSmsService` calls SUCCESS apply with `BANK_SMS_AUTO`.

## Production checks still required

The target environment must provide:

- production MySQL;
- production TLS certificates;
- real bank sender/SMS formats;
- actual receiving account last-4 values;
- organisation Android signing key;
- live SIM/device network conditions.

Run:

```bash
php bin/migrate.php
php bin/sms_match_preflight.php
```

Then execute every case in `DEVELOPER_DEPLOYMENT_GUIDE_TELUGU.md` section **Required Live Tests** before enabling real collections.
