package in.yeedipay.bankrelay;

public class ParserHarness {
    private static int passed = 0;

    public static void main(String[] args) {
        check("ICICI normal", "VK-ICICIB",
                "A/c XX1234 credited with Rs.500.00. UPI Ref No 627032899714. ICICI Bank",
                "ICICI", "500.00", "627032899714", "1234", true);

        check("amount before received", "VK-ICICIB",
                "Rs.250.00 received in A/c XX9999 via UPI. UTR 627032899714.",
                "ICICI", "250.00", "627032899714", "9999", true);


        check("IndusInd normal", "VM-INDUSB",
                "A/c XX3456 credited with INR 700.00 via UPI. UPI Ref No 627032899715.",
                "INDUSIND", "700.00", "627032899715", "3456", true);

        check("RBL slash UPI", "AD-RBLBNK",
                "Your A/c XX2222 is credited with INR 1250.00 via UPI/627032899714/RAO.",
                "RBL", "1250.00", "627032899714", "2222", true);

        check("UPI Txn ID", "VK-ICICIB",
                "A/c XX1234 credited by INR 99.00. UPI Txn ID 627032899714.",
                "ICICI", "99.00", "627032899714", "1234", true);

        check("security footer credit", "VK-ICICIB",
                "A/c XX1234 credited with Rs.500.00. UPI Ref 627032899714. Never share OTP/PIN.",
                "ICICI", "500.00", "627032899714", "1234", true);

        check("actual OTP ignored", "VK-ICICIB",
                "OTP 123456 for transaction of Rs 500. Do not share your OTP.",
                null, null, null, null, false);

        check("debit ignored", "AD-RBLBNK",
                "A/c XX2222 debited by INR 1000.00. Ref No 123456789012",
                null, null, null, null, false);

        check("debit request received ignored", "AD-RBLBNK",
                "Your payment request received. A/c XX2222 debited by INR 1000.00. UPI Ref No 123456789012.",
                null, null, null, null, false);

        check("debit received footer ignored", "AD-RBLBNK",
                "A/c XX2222 debited by INR 1000.00. Ref No 123456789012. Complaint request received.",
                null, null, null, null, false);


        check("collect request received ignored", "VK-ICICIB",
                "UPI collect request received for Rs.500.00 on A/c XX1234. UPI Ref No 627032899714.",
                null, null, null, null, false);

        check("request to pay received ignored", "VK-ICICIB",
                "You have received a request to pay Rs.500.00 from ABC. A/c XX1234. UPI Ref No 627032899714.",
                null, null, null, null, false);

        check("unknown sender ignored", "UNKNOWN",
                "A/c XX1234 credited with Rs.500.00. UPI Ref No 627032899714. ICICI Bank",
                null, null, null, null, false);

        check("payment received positive", "VK-ICICIB",
                "Payment received via UPI in A/c XX1234 for Rs.75.00. UPI Ref No 627032899716.",
                "ICICI", "75.00", "627032899716", "1234", true);

        check("future credit ignored", "VK-ICICIB",
                "A/c XX1234 will be credited with Rs.500.00. UPI Ref No 627032899714.",
                null, null, null, null, false);

        check("refund future credit ignored", "VK-ICICIB",
                "Refund of Rs.500.00 will be credited to A/c XX1234. UPI Ref No 627032899714.",
                null, null, null, null, false);

        check("credited back failed ignored", "VK-ICICIB",
                "A/c XX1234 credited back with Rs.500.00 due to failed UPI txn. UPI Ref No 627032899714.",
                null, null, null, null, false);

        check("pending UPI ignored", "VK-ICICIB",
                "UPI transaction pending. A/c XX1234 credited with Rs.500.00 after settlement. UPI Ref No 627032899714.",
                null, null, null, null, false);

        check("cashback ignored", "VK-ICICIB",
                "Cashback credited to A/c XX1234 for Rs.500.00 via UPI. UPI Ref No 627032899714.",
                null, null, null, null, false);

        check("non UPI credit ignored", "VK-ICICIB",
                "A/c XX1234 credited with Rs.500.00. NEFT Ref No ABCD1234567890.",
                null, null, null, null, false);

        System.out.println("ParserHarness PASS cases=" + passed);
    }

    private static void check(String name, String sender, String body,
                              String bank, String amount, String ref, String last4,
                              boolean expectedPresent) {
        BankTransaction tx = BankSmsParser.parse(sender, body, 1000L);
        if (!expectedPresent) {
            if (tx != null) throw new AssertionError(name + ": expected ignored but parsed");
            passed++;
            return;
        }
        if (tx == null) throw new AssertionError(name + ": parser returned null");
        if (!bank.equals(tx.bank)) throw new AssertionError(name + ": bank=" + tx.bank);
        if (!amount.equals(tx.amount.toPlainString())) throw new AssertionError(name + ": amount=" + tx.amount);
        if (!ref.equals(tx.reference)) throw new AssertionError(name + ": ref=" + tx.reference);
        if (last4 == null ? tx.accountLast4 != null : !last4.equals(tx.accountLast4)) {
            throw new AssertionError(name + ": last4=" + tx.accountLast4);
        }
        passed++;
    }
}
