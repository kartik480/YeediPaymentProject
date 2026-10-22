# YEEDIPAY Demo SMS Gateway (PHP)

This is a **local/demo gateway** for testing the YEEDIPAY PHP/MySQL application's SMS integration.

## Important
It **does not send a real SMS to a mobile phone**. It accepts the same HTTP request that the YEEDIPAY app sends, stores the message in `storage/sms_log.jsonl`, and returns HTTP 200 / ACCEPTED.

Use this to test:
- Customer OTP SMS flow
- Payment PENDING SMS
- SUCCESS SMS
- FAILED SMS
- EXPIRED SMS
- SMS outbox/retry integration
- Admin "Send Test SMS"

## Deploy
Point a temporary HTTPS subdomain, for example:

`https://sms-demo.yeedipay.in`

Document root:

`YEEDIPAY_SMS_DEMO_GATEWAY/public`

PHP 8.1+ is sufficient. Make sure PHP can write to:

`YEEDIPAY_SMS_DEMO_GATEWAY/storage/`

## Demo token
Current package token:

`42e8907d0c27dcaab8c2219577b3d24c`

Change the token in `config.php` before exposing the demo gateway publicly.

## Put these values in YEEDIPAY Admin > SMS Gateway

Gateway Name:
`YEEDIPAY Demo SMS Gateway`

Endpoint URL:
`https://sms-demo.yeedipay.in/send.php`

HTTP Method:
`POST`

Content Type:
`json`

Auth Header Name:
`Authorization`

Auth Prefix:
`Bearer `

Auth Token:
`42e8907d0c27dcaab8c2219577b3d24c`

Sender ID:
`YEEDIPAY`

Request Template:
```json
{"phone":"{phone}","message":"{message}","sender_id":"{sender_id}","template_id":"{template_id}"}
```

Then enable the gateway and use **Send Test SMS**.

## View demo messages
Open:

`https://sms-demo.yeedipay.in/inbox.php?key=42e8907d0c27dcaab8c2219577b3d24c`

Messages appear there. They are not delivered to a real phone.

## cURL test
```bash
curl -X POST https://sms-demo.yeedipay.in/send.php \
  -H 'Authorization: Bearer 42e8907d0c27dcaab8c2219577b3d24c' \
  -H 'Content-Type: application/json' \
  -d '{"phone":"919999999999","message":"YEEDIPAY test SMS","sender_id":"YEEDIPAY","template_id":""}'
```

Expected response:
```json
{"success":true,"status":"ACCEPTED","demo":true,"delivered_to_mobile":false}
```

## Move to a real SMS provider later
In YEEDIPAY Admin, replace only the gateway Endpoint/Auth/Request Template with the real SMS provider's values. The OTP and transaction SMS workflow does not need to be redesigned.
