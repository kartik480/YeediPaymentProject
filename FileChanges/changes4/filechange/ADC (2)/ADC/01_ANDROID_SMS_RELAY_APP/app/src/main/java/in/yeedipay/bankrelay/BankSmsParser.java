package in.yeedipay.bankrelay;

import java.math.BigDecimal;
import java.math.RoundingMode;
import java.util.ArrayList;
import java.util.List;
import java.util.Locale;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

public final class BankSmsParser {

    private static final Pattern OTP_CODE_PATTERN = Pattern.compile(
            "(?i)(?:\\b(?:otp|one[ -]?time password|verification code|auth(?:entication)? code)\\b[^0-9]{0,24}\\b[0-9]{4,8}\\b"
            + "|\\b[0-9]{4,8}\\b[^A-Za-z0-9]{0,24}\\b(?:otp|one[ -]?time password|verification code|auth(?:entication)? code)\\b)");

    // Strong positive credit wording. Generic "received" is deliberately handled
    // separately with account context because request/notification messages also use it.
    private static final Pattern UPI_CONTEXT_PATTERN = Pattern.compile("(?i)\\bUPI\\b");

    private static final Pattern STRONG_CREDIT_PATTERN = Pattern.compile(
            "(?i)\\b(credited|credit\\s+received|money\\s+received|payment\\s+received|has\\s+been\\s+credited)\\b");

    private static final Pattern RECEIVED_IN_ACCOUNT_PATTERN = Pattern.compile(
            "(?i)\\breceived\\b(?=.{0,90}\\b(?:in|into|to)\\s+(?:your\\s+)?(?:a/c|acct|account|ac)\\b)");

    // False-positive protection: debit/request alerts can contain a generic word such
    // as "received" together with amount + reference. Any clear outflow wording rejects it.
    private static final Pattern DEBIT_OR_OUTFLOW_PATTERN = Pattern.compile(
            "(?i)\\b(debited|debit(?:ed)?|withdrawn|spent|amount\\s+paid|payment\\s+(?:made|sent)|money\\s+sent)\\b");

    // Reject collect/payment/mandate requests. These are not incoming settled credits.
    private static final Pattern NON_CREDIT_REQUEST_PATTERN = Pattern.compile(
            "(?i)(?:\\b(?:upi\\s+)?collect\\s+request\\b|"
            + "\\bpayment\\s+request\\b|"
            + "\\brequest\\s+to\\s+pay\\b|"
            + "\\breceived\\s+(?:a\\s+)?request\\s+to\\s+pay\\b|"
            + "\\bmandate\\s+request\\b|"
            + "\\bpay\\s+request\\b)");

    // Reject non-final, reversal, refund and promotional credits. For payment confirmation,
    // a false SUCCESS is worse than a missed SMS that goes to review/manual investigation.
    private static final Pattern NON_FINAL_OR_NON_PAYMENT_CREDIT_PATTERN = Pattern.compile(
            "(?i)(?:"
            + "\\b(?:will|shall)\\s+be\\s+credited\\b|"
            + "\\bto\\s+be\\s+credited\\b|"
            + "\\bcredited\\s+back\\b|"
            + "\\b(?:transaction|payment|transfer|upi)\\s+(?:is\\s+)?pending\\b|"
            + "\\bpending\\s+(?:transaction|payment|transfer|upi)\\b|"
            + "\\b(?:failed|declined|unsuccessful)\\b|"
            + "\\brefund(?:ed)?\\b|"
            + "\\breversal\\b|\\breversed\\b|"
            + "\\bchargeback\\b|"
            + "\\bcashback\\b|"
            + "\\breward(?:s)?\\b|"
            + "\\bscheduled\\s+(?:credit|payment|transfer)\\b"
            + ")");

    private static final Pattern CURRENCY_AMOUNT_PATTERN = Pattern.compile(
            "(?i)(?:₹|INR\\s*|RS\\.?\\s*)([0-9][0-9,]*(?:\\.[0-9]{1,2})?)");

    private static final Pattern CREDIT_BARE_AMOUNT_PATTERN = Pattern.compile(
            "(?i)\\b(?:credited|received)\\b\\s*(?:by|with|for|of)?\\s*([0-9][0-9,]*(?:\\.[0-9]{1,2})?)");

    private static final Pattern[] REF_PATTERNS = new Pattern[]{
            Pattern.compile("(?i)(?:UPI\\s*(?:(?:REF(?:ERENCE)?|TXN|TRANSACTION)(?:\\s*(?:NO|ID))?\\.?)?|UTR|RRN|REF(?:ERENCE)?(?:\\s*(?:NO|ID))?\\.?)\\s*[:#./-]*\\s*([A-Z0-9]{10,35})"),
            Pattern.compile("(?i)\\bUPI\\s*[/:-]\\s*(?:CR\\s*[/:-]\\s*)?([A-Z0-9]{10,35})(?:\\s*[/:-]|\\b)")
    };

    private static final Pattern[] ACCOUNT_LAST4_PATTERNS = new Pattern[]{
            Pattern.compile("(?i)(?:A/C|ACCT|ACCOUNT|AC)[^0-9]{0,16}(?:X+|\\*+)?([0-9]{4})\\b"),
            Pattern.compile("(?i)\\b(?:ending|ends)\\s*(?:in|with)?\\s*(?:X+|\\*+)?([0-9]{4})\\b")
    };

    private BankSmsParser() {}

    public static BankTransaction parse(String sender, String body, long receivedAtMillis) {
        if (sender == null || body == null || body.trim().isEmpty()) return null;

        // Reject actual OTP-code messages. Security footers such as "never share OTP/PIN"
        // are not rejected unless an OTP-like code is present next to OTP wording.
        if (OTP_CODE_PATTERN.matcher(body).find()) return null;

        // Fail closed on outflow/request/non-final/reversal/refund wording.
        if (DEBIT_OR_OUTFLOW_PATTERN.matcher(body).find()) return null;
        if (NON_CREDIT_REQUEST_PATTERN.matcher(body).find()) return null;
        if (NON_FINAL_OR_NON_PAYMENT_CREDIT_PATTERN.matcher(body).find()) return null;

        String bank = BankRules.identifyBankFromSender(sender);
        if (bank == null) return null;

        // ICICI credit alerts often omit the word UPI and only say
        // "has been credited" plus a REF No. Still reject NEFT/IMPS/RTGS/interest.
        if (!UPI_CONTEXT_PATTERN.matcher(body).find()
                && Pattern.compile("(?i)\\b(?:NEFT|IMPS|RTGS|INTEREST)\\b").matcher(body).find()) {
            return null;
        }

        int creditIndex = findCreditIndex(body);
        if (creditIndex < 0) return null;

        BigDecimal amount = extractAmount(body, creditIndex);
        String reference = extractReference(body);
        if (amount == null || amount.compareTo(BigDecimal.ZERO) <= 0) return null;
        // Locked flow: bank UTR/RRN and receiving account last-4 are mandatory.
        // If either is absent, the SMS cannot confirm a payment.
        if (reference == null || reference.trim().isEmpty()) return null;

        String accountLast4 = extractAccountLast4(body);
        if (accountLast4 == null || accountLast4.trim().isEmpty()) return null;
        return new BankTransaction(
                bank,
                amount.setScale(2, RoundingMode.HALF_UP),
                reference.toUpperCase(Locale.ROOT),
                accountLast4,
                sender,
                receivedAtMillis
        );
    }

    private static int findCreditIndex(String body) {
        Matcher strong = STRONG_CREDIT_PATTERN.matcher(body);
        Matcher receivedInAccount = RECEIVED_IN_ACCOUNT_PATTERN.matcher(body);
        int a = strong.find() ? strong.start() : -1;
        int b = receivedInAccount.find() ? receivedInAccount.start() : -1;
        if (a < 0) return b;
        if (b < 0) return a;
        return Math.min(a, b);
    }

    private static BigDecimal extractAmount(String body, int creditIndex) {
        List<AmountCandidate> candidates = new ArrayList<>();
        Matcher m = CURRENCY_AMOUNT_PATTERN.matcher(body);
        while (m.find()) {
            try {
                BigDecimal value = new BigDecimal(m.group(1).replace(",", ""));
                int distance = Math.abs(m.start() - creditIndex);
                candidates.add(new AmountCandidate(value, distance));
            } catch (Exception ignored) {}
        }

        AmountCandidate best = null;
        for (AmountCandidate c : candidates) {
            if (best == null || c.distance < best.distance) best = c;
        }
        if (best != null && best.distance <= 120) return best.value;

        m = CREDIT_BARE_AMOUNT_PATTERN.matcher(body);
        if (m.find()) {
            try {
                return new BigDecimal(m.group(1).replace(",", ""));
            } catch (Exception ignored) {}
        }
        return null;
    }

    private static String extractReference(String body) {
        for (Pattern p : REF_PATTERNS) {
            Matcher m = p.matcher(body);
            if (m.find()) {
                String ref = m.group(1).replaceAll("[^A-Za-z0-9]", "");
                if (ref.length() >= 10 && ref.length() <= 35) return ref;
            }
        }
        return null;
    }

    private static String extractAccountLast4(String body) {
        for (Pattern p : ACCOUNT_LAST4_PATTERNS) {
            Matcher m = p.matcher(body);
            if (m.find()) return m.group(1);
        }
        return null;
    }

    private static final class AmountCandidate {
        final BigDecimal value;
        final int distance;
        AmountCandidate(BigDecimal value, int distance) {
            this.value = value;
            this.distance = distance;
        }
    }
}
