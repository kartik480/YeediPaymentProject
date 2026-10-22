# YEEDIPAY — Customer Totals / Ledger Accuracy Patch

## ముందుగా తెలుసుకోవాల్సిన విషయం
Live `CUST0003` pageలో ప్రస్తుతం కనిపిస్తున్న PENDING rows మొత్తం **₹207.15**. కాబట్టి ₹207.15 top card తప్పు కాదు. Earlier ₹206.15 calculation arithmetic mistake.

Visible PENDING rows:
- ₹1.00
- ₹99.85
- ₹1.61
- ₹100.69
- ₹1.00
- ₹1.00
- ₹1.00
- ₹1.00

Total = **₹207.15**.

## ఈ patch ఎందుకు అవసరం
Current sourceలో నిజమైన accounting issues ఉన్నాయి:
1. `FAILED` transactionsను `Refunded`గా sum చేస్తోంది — ఇది తప్పు.
2. Super Admin ledger balance SUCCESS మొత్తం మాత్రమే చూపిస్తోంది; SETTLED payouts debit చేయడం లేదు.
3. Super Admin operating-mode text ఇంకా manual confirmation అని చూపించగలదు; current locked flow Bank SMS Relay.
4. Customer filters use చేసినప్పుడు top cards all-time totals అని UI స్పష్టంగా చెప్పడం లేదు.
5. Totals verify చేయడానికి safe CLI checker లేదు.

## Locked payment flow — మార్చవద్దు
Customer amount → unique exact QR amount → payment → I HAVE PAID → 30-sec checking → Bank CREDIT SMS → Android Relay → exact bank/account/payable amount/UTR match → SUCCESS → UTR display → service release.

ఈ patch **payment confirmation codeను మార్చదు**.

---

# 1) Backup

```bash
cd <PROJECT_ROOT>
cp app/TransactionService.php app/TransactionService.php.before_totals_fix
cp app/SuperAdminService.php app/SuperAdminService.php.before_totals_fix
cp customer/views/dashboard.php customer/views/dashboard.php.before_totals_fix
cp customer/views/statement.php customer/views/statement.php.before_totals_fix
cp super-admin/views/customer_accounts.php super-admin/views/customer_accounts.php.before_totals_fix
```

DB backup కూడా తీసుకోండి:

```bash
mysqldump -u DB_USER -p DB_NAME > ../yeedipay_before_customer_totals_fix.sql
```

---

# 2) Copy/Paste these files exactly

Patch ZIP extract చేసిన తర్వాత:

```text
PATCH_FILES/app/TransactionService.php
→ <PROJECT_ROOT>/app/TransactionService.php

PATCH_FILES/app/SuperAdminService.php
→ <PROJECT_ROOT>/app/SuperAdminService.php

PATCH_FILES/customer/views/dashboard.php
→ <PROJECT_ROOT>/customer/views/dashboard.php

PATCH_FILES/customer/views/statement.php
→ <PROJECT_ROOT>/customer/views/statement.php

PATCH_FILES/super-admin/views/customer_accounts.php
→ <PROJECT_ROOT>/super-admin/views/customer_accounts.php

PATCH_FILES/bin/customer_totals_check.php
→ <PROJECT_ROOT>/bin/customer_totals_check.php
```

**Do not overwrite** `.env`, BankSmsService, ReconciliationService, Android Relay files, QR flow files, or live secrets with this patch.

---

# 3) What this patch fixes

### Pending Amount
`PENDING` = `SUM(requested_amount)` only.

### Confirmed Collections
`SUCCESS` = `SUM(COALESCE(confirmed_amount, requested_amount))`.

### Refunded
`FAILED` / `EXPIRED` are **not refunds**. This build has no dedicated bank-confirmed refund ledger, so Refunded stays ₹0.00 until a real refund ledger/module is connected.

### Settled
Only `SUCCESS` transactions with `settlement_status='SETTLED'` are treated as settled debit.

### Ledger Balance

```text
Ledger Balance = Confirmed Collections - Settled - Refunded
```

Current build:

```text
Refunded = 0.00
```

Therefore:

```text
Ledger Balance = Confirmed Collections - Settled
```

Do not clamp negative ledger silently; a negative balance should expose an accounting inconsistency for investigation.

---

# 4) Syntax check

```bash
php -l app/TransactionService.php
php -l app/SuperAdminService.php
php -l customer/views/dashboard.php
php -l customer/views/statement.php
php -l super-admin/views/customer_accounts.php
php -l bin/customer_totals_check.php
```

Every command must say:

```text
No syntax errors detected
```

---

# 5) Live totals verification

For the current customer:

```bash
php bin/customer_totals_check.php CUST0003
```

Expected current Pending Amount before transaction status changes:

```text
Pending : ₹207.15
```

The last line must be:

```text
TOTALS CHECK PASS
```

If live transactions change between checking and testing, the amount may change; PASS/FAIL is authoritative.

---

# 6) Direct SQL cross-check

```sql
SELECT
  COUNT(*) AS pending_count,
  ROUND(COALESCE(SUM(requested_amount),0),2) AS pending_amount
FROM transactions t
JOIN customers c ON c.id=t.customer_id
WHERE c.code='CUST0003'
  AND t.status='PENDING';
```

`pending_amount` must equal Customer Dashboard Pending Amount.

Check FAILED separately:

```sql
SELECT COUNT(*) failed_count,
       ROUND(COALESCE(SUM(requested_amount),0),2) failed_attempt_amount
FROM transactions t
JOIN customers c ON c.id=t.customer_id
WHERE c.code='CUST0003'
  AND t.status='FAILED';
```

This amount must **not** appear under Refunded.

---

# 7) Mandatory regression tests

1. Existing CUST0003 dashboard must continue opening.
2. Current PENDING rows sum must equal Pending card.
3. Create one PENDING ₹10 transaction → Pending card must increase by ₹10 base requested amount.
4. Do not press I HAVE PAID / no valid SMS → must remain PENDING.
5. Valid Bank SMS Relay SUCCESS → Pending decreases and Confirmed increases only after valid match.
6. Mark/test FAILED transaction → Refunded must remain unchanged.
7. SETTLED SUCCESS → Settled increases and Ledger Balance decreases by same confirmed amount.
8. UTR display, exact payable amount, Android Relay, nonce/HMAC, account-last4, unique paise matching must continue unchanged.
9. Run `php bin/customer_totals_check.php CUST0003` again; final line must be `TOTALS CHECK PASS`.

---

# 8) Important UI behavior

Top account cards are **all-time account totals**. Status / Period / From / To filters below apply to transaction history/export only. This patch adds that explanation so future filtered lists are not mistaken for headline account totals.

---

# 9) Do NOT do these

- Do not create a fake ₹1 adjustment to “fix” CUST0003; ₹207.15 is already correct.
- Do not convert FAILED to REFUND.
- Do not manually mark payment SUCCESS.
- Do not touch the locked Bank SMS matching flow.
- Do not overwrite production `.env` or Android signing keystore.
- Do not replace latest security/nonce BankSmsService with an older copy.

## Final acceptance
Go live only after PHP syntax tests pass, `customer_totals_check.php` passes, and one fresh live payment completes through the locked SMS Relay flow.
