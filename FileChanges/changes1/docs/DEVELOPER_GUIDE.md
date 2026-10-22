# Developer Guide — ADC Current Flow

The only collection-confirmation path is:

**Amount → QR → payment → I HAVE PAID → 30-sec verification → bank UPI CREDIT SMS → Android Relay → bank/account + amount + UTR/RRN verification → SUCCESS → UTR display → service release.**

Key implementation files:

- `app/TransactionService.php` — attempt creation, acknowledgement, 30-second verification state.
- `customer/views/_paid_verify.php` — customer verification UI/polling.
- `app/BankSmsService.php` — authenticated relay ingestion and automatic matching.
- `app/ReconciliationService.php` — hard guard: collection SUCCESS only from `BANK_SMS_AUTO`.
- `customer/public/index.php` — acknowledge, status and bank-relay endpoint.
- `android_sms_relay/` — SMS parser and signed HTTPS uploader.

Required bank SMS data for SUCCESS:

- supported bank sender;
- UPI CREDIT wording;
- exact amount;
- receiving account last-4;
- valid unique UTR/RRN/reference.

Run `php bin/migrate.php` and `php bin/sms_match_preflight.php` after deployment. See the root `DEVELOPER_DEPLOYMENT_GUIDE_TELUGU.md` for detailed copy/paste and testing instructions.
