# YEEDI Android SMS Relay — V5 Core Developed

Purpose: receive supported bank UPI CREDIT SMS on the dedicated Android phone, parse only transaction metadata, and securely upload it to the Server Core.

## Server endpoint
`https://YOUR-DOMAIN/.../02_SERVER_CORE/api/relay/webhook_bank_sms.php`

## Phone configuration
1. Install the app on the dedicated bank-SIM phone.
2. Allow **Receive SMS** permission.
3. Enter the HTTPS endpoint.
4. Enter Device ID and API Secret created by `02_SERVER_CORE/cli/provision_device.php`.
5. Enter the last 4 digits of each configured ICICI / IndusInd / RBL receiving account.
6. Save configuration and run the safe test event.

## Important
- Raw SMS body is not uploaded.
- Actual OTP code SMS is rejected on the phone.
- The app uploads bank, amount, UTR/RRN/reference, account last4, sender ID and original SMS received time.
- Server HMAC verification and exact sender allowlist remain mandatory.

## APK build
Open this folder in Android Studio using JDK 17 compatibility and build an APK. The source is build-ready, but the final APK should be signed with your organisation's Android signing key before production installation.

## Delayed SMS handling
If a bank alert arrives after 30 seconds, the Android app still uploads the original SMS received timestamp and WorkManager retries transient network failures. The Server Core remains authoritative and can match the delayed event inside its configured payment/grace window. The 30-second value is only the customer verification UX; it is not an SMS expiry.
