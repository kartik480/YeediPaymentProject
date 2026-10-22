# YEEDIPAY ADC — CURRENT FLOW FINAL DEVELOPER GUIDE

Version: **SMS_MATCH_CURRENT_FLOW_FINAL_2026-09-30**

## 1. LOCKED PAYMENT FLOW

ఈ projectలో payment collectionకి production flow ఇదొక్కటే:

**Customer amount → QR → payment → I HAVE PAID → 30-sec checking → Bank UPI CREDIT SMS → Android Relay → Server amount + receiving account + bank UTR/RRN verification → SUCCESS → UTR display → service release**

### SUCCESS రావడానికి అన్ని conditions తప్పనిసరి

1. Transaction `PENDING`లో ఉండాలి.
2. Customer `I HAVE PAID — CHECK NOW` నొక్కి ఉండాలి (`customer_paid_ack_at`).
3. SMS approved bank sender నుంచి వచ్చిన **UPI CREDIT** message కావాలి.
4. SMSలో exact credited amount ఉండాలి.
5. SMSలో receiving account **last 4 digits** తప్పనిసరిగా ఉండాలి.
6. ఆ last-4 Admin → Bank SMSలో registered accountతో match కావాలి.
7. SMSలో bank **UTR/RRN/reference** తప్పనిసరిగా ఉండాలి.
8. ఆ UTR/RRN valid formatలో ఉండాలి మరియు ఇప్పటికే మరో transactionకి use అయి ఉండకూడదు.
9. Same bank/account + same amount + valid time windowలో ఒక acknowledged pending candidate మాత్రమే ఉండాలి.
10. పై verification తరువాత మాత్రమే `SUCCESS` write అవుతుంది.
11. `SUCCESS` అయిన తరువాత bank UTR/RRN customer screenలో చూపాలి; service activation తరువాత మాత్రమే జరగాలి.

`I HAVE PAID` button payment proof కాదు. Button verification processను మాత్రమే start చేస్తుంది.

---

## 2. DEVELOPERకి ఇచ్చే రెండు installation methods

### Method A — Recommended: full package replace

`ADC_SMS_MATCH_CURRENT_FLOW_FINAL.zip` extract చేసి application rootలో files copy చేయాలి.

**Live serverలో existing `.env` మరియు runtime storage/backup filesను delete/overwrite చేయకండి.** Packageలో `.env.example` మాత్రమే source templateగా ఉంది.

### Method B — Existing serverపై patch files మాత్రమే

`ADC_SMS_MATCH_CURRENT_FLOW_PATCH_ONLY.zip`లో ఉన్న relative pathsను అదే pathsలో overwrite చేయాలి.

Patchలో ముఖ్యమైన paths:

```text
app/BankSmsService.php
app/ReconciliationService.php
app/PaymentConfirmService.php
customer/public/index.php
admin/public/index.php
admin/views/banks.php
admin/views/bank_sms.php
android_sms_relay/app/src/main/java/in/yeedipay/bankrelay/BankSmsParser.java
android_sms_relay/app/src/main/java/in/yeedipay/bankrelay/UploadWorker.java
android_sms_relay/app/src/test/java/in/yeedipay/bankrelay/BankSmsParserTest.java
DEVELOPER_DEPLOYMENT_GUIDE_TELUGU.md
FINAL_QA_REPORT.md
README.md
docs/DEVELOPER_GUIDE.md
docs/FUNCTION_MATRIX.md
docs/GO_LIVE_CHECKLIST.md
VERSION.txt
```

---

## 3. SERVER PATCH — `app/BankSmsService.php`

ఈ file payment confirmation engine.

### A. SMS reference mandatory validation

`ingest()` methodలో `$utr`, `$last4`, `$amount` parse అయిన తరువాత ఈ validation ఉండాలి:

```php
if ($utr === '') {
    throw new InvalidArgumentException('Bank UTR/RRN reference is required');
}
if (!preg_match('/^[A-Z0-9]{8,35}$/', $utr)) {
    throw new InvalidArgumentException('Bank reference format is not valid');
}
if (!preg_match('/^\d{4}$/', $last4)) {
    throw new InvalidArgumentException('Account last 4 is required');
}
```

### B. Receiving account mapping

దాని వెంటనే:

```php
$account = self::matchingAccount($db, $device, $bankCode, $last4);
if (!$account) {
    throw new InvalidArgumentException('Bank/account cannot be uniquely mapped to this relay phone');
}
$bankCode = strtoupper((string)$account['bank_code']);
$last4 = (string)$account['account_last4'];
```

ఇది bank SMSలో వచ్చిన account last-4ను Adminలో registered receiving accountతో verify చేస్తుంది.

### C. Candidate selection

Auto match queryలో transactionకి ఇవి తప్పనిసరి:

```sql
status='PENDING'
customer_paid_ack_at IS NOT NULL
requested_amount=?
bank_id IN (...)
customer_paid_ack_at within matching window
created_at within matching window
```

Bank SMS eventలో ఇవి తప్పనిసరి:

```text
bank/profile verified
account_last4 verified
exact amount verified
UTR/RRN present
UTR/RRN unique
UPI CREDIT classification
```

ఒక్క transaction candidate ఉంటే మాత్రమే `confirmFromSmsEvent()` run చేయాలి.

### D. SUCCESS source

`confirmFromSmsEvent()` చివరలో reconciliation call source exactగా ఇలా ఉండాలి:

```php
], 'BANK_SMS_AUTO');
```

### E. Manual attach block

Method ఈ విధంగా lock అయి ఉండాలి:

```php
public static function attachManually(PDO $db, int $eventId, string $reference): string {
    throw new RuntimeException(
        'Manual payment confirmation is disabled. Verified Android bank SMS matching is required.'
    );
}
```

---

## 4. SUCCESS WRITE GUARD — `app/ReconciliationService.php`

`apply()`లో `SUCCESS`కి ఈ guard తప్పనిసరి:

```php
if ($status === 'SUCCESS' && strtoupper(trim($source)) !== 'BANK_SMS_AUTO') {
    return [false, 'SUCCESS requires the authenticated Android bank SMS auto-match flow'];
}
```

అంటే applicationలో ఎవరు status API, CSV, form, webhook లేదా ఇతర source నుంచి `SUCCESS` పంపినా transaction credit కాకూడదు.

---

## 5. PROVIDER STATUS CHECK — `app/PaymentConfirmService.php`

`refresh()` collection statusను మార్చకూడదు. Current final code:

```php
public static function refresh(PDO $db, array $t): array {
    return TransactionService::txnByRef(
        $db,
        (string)($t['reference'] ?? '')
    ) ?: $t;
}
```

QR/order connectivity separately ఉండవచ్చు; collection confirmation మాత్రం Android Bank SMS Relay ద్వారానే జరగాలి.

---

## 6. BANK CALLBACK ROUTES — BLOCKED

ఈ రెండు filesలో `/api/banks/{BANKCODE}/webhook` collection confirmation route 410 response ఇవ్వాలి:

```text
customer/public/index.php
admin/public/index.php
```

Required code:

```php
if (preg_match('#^/api/banks/([A-Z0-9]+)/webhook$#', $path, $m) && $method === 'POST') {
    json_response([
        'ok' => false,
        'error' => 'collection_confirmation_disabled',
        'message' => 'Use the authenticated Android bank SMS relay endpoint.'
    ], 410);
}
```

---

## 7. CUSTOMER `I HAVE PAID` ROUTES

File:

```text
customer/public/index.php
```

Required routes:

```text
POST /pay/{REFERENCE}/acknowledge
GET  /pay/{REFERENCE}/status
POST /api/bank-relay/webhook_bank_sms.php
```

### Acknowledge route

Customer button click చేసిన వెంటనే:

```text
customer_paid_ack_at = UTC_TIMESTAMP()
```

అది success చేయదు. తరువాత server matching process run అవుతుంది.

### Status route

Page polling ప్రతి కొన్ని secondsకి transaction state check చేస్తుంది. Bank SMS lateగా వస్తే అదే pending transaction తరువాత auto-confirm అవుతుంది.

---

## 8. 30-SECOND CUSTOMER UI

File:

```text
customer/views/_paid_verify.php
```

Required behaviour:

```text
I HAVE PAID — CHECK NOW
        ↓
VERIFYING PAYMENT… (30 seconds)
        ↓
Bank SMS already matched → PAYMENT SUCCESSFUL
        OR
SMS delayed → PAYMENT SUBMITTED / Waiting for bank SMS confirmation
        ↓
Later verified SMS → PAYMENT SUCCESSFUL
```

Customerకి “pay again” చేయమని చెప్పకూడదు while verification is pending.

---

## 9. ANDROID PARSER — `BankSmsParser.java`

File:

```text
android_sms_relay/app/src/main/java/in/yeedipay/bankrelay/BankSmsParser.java
```

Final mandatory block:

```java
BigDecimal amount = extractAmount(body, creditIndex);
String reference = extractReference(body);
if (amount == null || amount.compareTo(BigDecimal.ZERO) <= 0) return null;
if (reference == null || reference.trim().isEmpty()) return null;

String accountLast4 = extractAccountLast4(body);
if (accountLast4 == null || accountLast4.trim().isEmpty()) return null;
```

Parser debit, OTP, collect request, pending, refund, reversal, cashback, non-UPI messagesను relay payment eventగా accept చేయకూడదు.

---

## 10. ANDROID UPLOAD — `UploadWorker.java`

Network uploadకి ముందు:

```java
if (bank == null || bank.trim().isEmpty()
        || reference == null || reference.trim().isEmpty()
        || accountLast4 == null || !accountLast4.matches("\\d{4}")
        || sender == null || sender.trim().isEmpty()
        || amount.signum() <= 0) {
    return Result.failure();
}
```

Endpoint:

```text
https://customer.yeedipay.in/api/bank-relay/webhook_bank_sms.php
```

Request must include:

```text
X-Device-Id
X-Timestamp
X-Nonce
X-Signature
```

Signature:

```text
HMAC-SHA256(secret, timestamp + "\n" + nonce + "\n" + raw_json)
```

Production app HTTPS-onlyగా ఉండాలి.

---

## 11. BANK SMS DEVICE SETUP

Admin:

```text
Admin → Bank SMS → Register parser device
```

ప్రతి relay phone/accountకి:

```text
Phone name
Bank
Receiving account last 4
Device ID
Secret
```

Android appలో అదే Device ID + Secret configure చేయాలి.

ఒక phoneలో multiple receiving accounts ఉంటే ప్రతి bank/account last-4 mapping Adminలో add చేయాలి.

---

## 12. DATABASE DEPLOYMENT

Server application rootలో:

```bash
php bin/migrate.php
php bin/sms_match_preflight.php
```

Expected:

```text
PREFLIGHT PASS
```

Critical DB objects:

```text
transactions.customer_paid_ack_at
transactions.sms_match
bank_sms_devices
bank_sms_device_accounts
portal_bank_sms_events
bank_sms_nonces
```

---

## 13. `.env` PRODUCTION SETTINGS

`.env.example` copy చేసి actual server `.env` configure చేయాలి:

```env
APP_ENV=production
APP_TIMEZONE=Asia/Kolkata
PAY_PUBLIC_URL=https://customer.yeedipay.in
SESSION_COOKIE_SECURE=true
BANK_SMS_MAX_EVENT_AGE_HOURS=24
```

ఇవి కూడా actual secure valuesతో ఉండాలి:

```text
APP_KEY
DB_HOST
DB_NAME
DB_USER
DB_PASS
```

Secrets source filesలో hard-code చేయకండి.

---

## 14. ANDROID PRODUCTION APK

Source folder:

```text
android_sms_relay/
```

Developer Android Studioలో:

1. Project open చేయాలి.
2. Release signing configuration organisation keystoreతో set చేయాలి.
3. `release` APK/AAB build చేయాలి.
4. Bank SMS SIM ఉన్న dedicated Android phoneలో install చేయాలి.
5. SMS permission allow చేయాలి.
6. Battery optimisation exemption ఇవ్వాలి.
7. Internet connectivity stableగా ఉండాలి.
8. Endpoint + Device ID + Secret configure చేయాలి.
9. Connection test చేయాలి.

Unsigned/debug APK productionలో deploy చేయకండి.

---

## 15. REQUIRED LIVE TESTS

### Test 1 — Correct payment

```text
₹500 attempt
→ QR payment
→ I HAVE PAID
→ SMS: account correct + ₹500 + valid UTR
→ SUCCESS
→ same UTR customer screenలో display
→ service release
```

### Test 2 — Wrong amount

```text
Order ₹500
SMS ₹499 / ₹501
→ SUCCESS రావద్దు
```

### Test 3 — Wrong account

```text
Amount correct
Account last-4 wrong
→ server reject
```

### Test 4 — Missing UTR/RRN

```text
Amount + account correct
Bank reference absent
→ SUCCESS రావద్దు
```

### Test 5 — Reused UTR

```text
Already-used UTR again
→ reject
```

### Test 6 — No I HAVE PAID acknowledgement

```text
SMS arrives
Customer button not tapped
→ SUCCESS రావద్దు
```

Customer తరువాత button నొక్కితే valid stored SMS/time window ఉంటే match కావచ్చు.

### Test 7 — Delayed SMS

```text
I HAVE PAID
→ 30 sec complete
→ WAITING_BANK_SMS
→ SMS later arrives
→ SUCCESS
```

### Test 8 — Same-amount collision

```text
Same receiving bank/accountలో రెండు acknowledged ₹500 pending payments
→ automatic SUCCESS కాదు
→ REVIEW_REQUIRED
```

### Test 9 — Debit/OTP/collect/refund

ఈ SMS types ఏదీ payment creditగా accept కాకూడదు.

### Test 10 — HMAC replay

Same nonce/request రెండోసారి:

```text
→ reject
```

---

## 16. FINAL GO-LIVE COMMANDS

```bash
php -l app/BankSmsService.php
php -l app/ReconciliationService.php
php -l app/PaymentConfirmService.php
php -l customer/public/index.php
php -l admin/public/index.php
php bin/migrate.php
php bin/sms_match_preflight.php
```

`PREFLIGHT PASS` + live tests PASS అయిన తరువాత మాత్రమే real collections enable చేయాలి.

---

## 17. FINAL ACCEPTANCE RULE

Developer final handoverలో ఈ statement confirm చేయాలి:

> A collection transaction can become SUCCESS only after an authenticated Android Relay bank UPI CREDIT SMS provides a valid unique UTR/RRN, the registered receiving account last-4 matches, the credited amount exactly matches, the customer has already tapped I HAVE PAID, and exactly one eligible pending transaction is identified. The same bank UTR/RRN is then displayed to the customer and only then is the service released.
