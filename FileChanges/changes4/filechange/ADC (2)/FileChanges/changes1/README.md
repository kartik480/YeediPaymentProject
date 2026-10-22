# YEEDIPAY ADC — CURRENT FLOW FINAL

Version: **SMS_MATCH_CURRENT_FLOW_FINAL_2026-09-30**

Production collection flow:

**Customer amount → QR → payment → I HAVE PAID → 30-sec checking → Bank UPI CREDIT SMS → Android Relay → Server amount/account/UTR-RRN verification → SUCCESS → UTR display → service release**

## Locked confirmation requirements

A payment can become `SUCCESS` only when all of these are true:

- customer already tapped `I HAVE PAID`;
- transaction is still `PENDING`;
- Android relay request is authenticated with Device ID, timestamp, nonce and HMAC;
- message is classified as a supported bank UPI CREDIT SMS;
- receiving account last-4 is present and matches the registered relay account;
- credited amount exactly matches;
- bank UTR/RRN is present, valid and unique;
- exactly one eligible acknowledged transaction is identified in the matching window.

The customer does not type the UTR. The bank UTR/RRN comes from the bank SMS and is displayed only after successful verification.

## Install

```bash
cp .env.example .env
# configure production DB + APP_KEY
php bin/install.php
php bin/create_admin.php admin 'Use-A-Strong-Password-Here'
php bin/migrate.php
php bin/sms_match_preflight.php
php bin/health_check.php
```

`php bin/sms_match_preflight.php` must return `PREFLIGHT PASS` before live collections.

## Android relay

Production endpoint:

```text
https://customer.yeedipay.in/api/bank-relay/webhook_bank_sms.php
```

Build and sign the Android release APK with the organisation signing key. Production relay is HTTPS-only.

See `DEVELOPER_DEPLOYMENT_GUIDE_TELUGU.md` for exact copy/paste paths, production setup and live test cases.
