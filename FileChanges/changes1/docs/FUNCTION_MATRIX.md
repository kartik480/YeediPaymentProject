# Function Matrix — ADC Current Flow

| Function | Status | Source of truth |
|---|---|---|
| Customer amount entry | Enabled | Customer payment page |
| QR generation/display | Enabled | Active receiving bank profile |
| I HAVE PAID acknowledgement | Enabled | `transactions.customer_paid_ack_at` |
| 30-second verification buffer | Enabled | `TransactionService::PAID_VERIFY_SECONDS` |
| Bank UPI CREDIT SMS parsing | Enabled | Android relay parser |
| Receiving account last-4 verification | Mandatory | Relay device/account mapping |
| Exact amount verification | Mandatory | Transaction vs bank SMS |
| Bank UTR/RRN verification | Mandatory | Bank SMS; must be valid + unique |
| Android request authentication | Mandatory | Device ID + timestamp + nonce + HMAC |
| Automatic SUCCESS | Enabled | `BANK_SMS_AUTO` only |
| UTR display after SUCCESS | Enabled | Stored bank RRN/UPI transaction reference |
| Service release | Enabled | After SUCCESS |
| Same-amount ambiguous auto-success | Disabled | `REVIEW_REQUIRED` |
| Provider callback collection SUCCESS | Disabled | HTTP 410 |
| Provider status collection update | Disabled | No status mutation |
| Manual SMS attach SUCCESS | Disabled | Runtime guard |
