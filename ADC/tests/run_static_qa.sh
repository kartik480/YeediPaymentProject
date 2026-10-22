#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
echo '[1/6] PHP syntax'
find "$ROOT/02_SERVER_CORE" -type f -name '*.php' -print0 | while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done
echo 'PASS'

echo '[2/6] Java parser'
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
javac -d "$TMP" \
  "$ROOT/01_ANDROID_SMS_RELAY_APP/app/src/main/java/in/yeedipay/bankrelay/BankTransaction.java" \
  "$ROOT/01_ANDROID_SMS_RELAY_APP/app/src/main/java/in/yeedipay/bankrelay/BankRules.java" \
  "$ROOT/01_ANDROID_SMS_RELAY_APP/app/src/main/java/in/yeedipay/bankrelay/BankSmsParser.java" \
  "$ROOT/tests/ParserHarness.java"
java -cp "$TMP" in.yeedipay.bankrelay.ParserHarness
echo 'PASS'

echo '[3/6] Android XML parse'
python3 - "$ROOT" <<'PY'
import sys, pathlib, xml.etree.ElementTree as ET
root=pathlib.Path(sys.argv[1])/'01_ANDROID_SMS_RELAY_APP/app/src/main'
for p in root.rglob('*.xml'): ET.parse(p)
print('PASS')
PY

echo '[4/6] Customer JS syntax'
node --check "$ROOT/03_CUSTOMER_PAYMENT_DASHBOARD/assets/app.js"
echo 'PASS'

echo '[5/6] Architecture files'
for f in \
  "$ROOT/02_SERVER_CORE/api/relay/webhook_bank_sms.php" \
  "$ROOT/02_SERVER_CORE/api/payments/create.php" \
  "$ROOT/02_SERVER_CORE/api/payments/status.php" \
  "$ROOT/02_SERVER_CORE/api/payments/acknowledge.php" \
  "$ROOT/02_SERVER_CORE/payment_core.php" \
  "$ROOT/03_CUSTOMER_PAYMENT_DASHBOARD/index.html"; do test -s "$f"; done
grep -q 'amount_slot_busy' "$ROOT/02_SERVER_CORE/payment_core.php"
grep -q "status IN ('PENDING','EXPIRED')" "$ROOT/02_SERVER_CORE/payment_core.php"
grep -q 'slotCutoffUtc' "$ROOT/02_SERVER_CORE/payment_core.php"
grep -q 'delivery_ready' "$ROOT/02_SERVER_CORE/payment_core.php"
grep -q 'VERIFIED SIGNED BANK CREDIT SMS ONLY' "$ROOT/02_SERVER_CORE/cli/system_check.php"
echo 'PASS'
echo '[6/6] 30-second verification assertions'
grep -q "customer_verification_buffer_seconds' => 30" "$ROOT/02_SERVER_CORE/config.php"
grep -q "verification_state" "$ROOT/02_SERVER_CORE/payment_core.php"
grep -q "WAITING_BANK_SMS" "$ROOT/02_SERVER_CORE/payment_core.php"
grep -q "verificationBox" "$ROOT/03_CUSTOMER_PAYMENT_DASHBOARD/index.html"
grep -q "VERIFYING PAYMENT" "$ROOT/03_CUSTOMER_PAYMENT_DASHBOARD/assets/app.js"
grep -q "DO NOT PAY AGAIN" "$ROOT/README_FIRST_TELUGU.md"
echo 'PASS'
echo 'FINAL STATIC QA PASS'
