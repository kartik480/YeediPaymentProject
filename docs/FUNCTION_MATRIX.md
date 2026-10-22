# YEEDIPAY PHP/MySQL Function Matrix

## Admin
- Separate Admin URL and session login
- Unlimited bank QR profiles
- Browser-side QR image decode; exact `upi://pay` payload storage
- Manual bank activation
- Automatic weighted rotation: each bank has order + active minutes
- Enable/disable bank
- Replace bank QR without changing customer links
- Unlimited customers; automatic Customer ID
- Customer enable/disable
- Per-transaction, daily, weekly, monthly amount limits
- Daily transaction-attempt count limit
- Maximum pending attempts per customer
- Customer payment link + permanent QR
- Day/week/month/custom transaction filters
- SUCCESS/PENDING/FAILED/EXPIRED filters
- CSV export
- Manual reconciliation
- Bulk bank CSV reconciliation
- Match by internal reference OR customer UTR/RRN OR bank RRN OR UPI transaction ID
- Duplicate UTR/RRN/UPI transaction protection
- Admin-only correction/clear of customer-reported UTR
- Over-limit SUCCESS credits are retained and marked LIMIT REVIEW
- Generic SMS gateway configuration and test
- OTP + PENDING/SUCCESS/FAILED/EXPIRED SMS templates
- SMS retry outbox
- Audit log
- Admin password change
- Admin login brute-force throttling
- Database-level unique bank RRN / UPI transaction constraints
- MySQL backup script with 7-day local retention
- Optional future bank webhook endpoint

## Customer
- Permanent customer payment link that does not change when active bank changes
- Exact active-bank UPI payload handoff to installed UPI apps
- No injected `tr`, `tn`, `am` or Customer ID into bank QR
- Optional declared amount; required when a Customer ID money limit exists
- Internal PENDING attempt ID
- UTR/RRN submission after payment
- Customer cannot replace a previously submitted UTR; Admin must correct it
- Public status polling exposes only status/amount/review flag
- OTP-protected statement dashboard
- Day/week/month/custom statements
- CSV statement download
- Permanent customer QR page

## Server / background
- MySQL/InnoDB schema
- UTC database timestamps; India calendar periods by APP_TIMEZONE
- 7-day PENDING reconciliation window (configurable)
- Automatic PENDING → EXPIRED worker
- SMS retry worker
- CSRF protection on all state-changing forms
- Prepared PDO queries
- Password hashing
- Encrypted SMS API token
- Secure session cookie options
- Health checks
