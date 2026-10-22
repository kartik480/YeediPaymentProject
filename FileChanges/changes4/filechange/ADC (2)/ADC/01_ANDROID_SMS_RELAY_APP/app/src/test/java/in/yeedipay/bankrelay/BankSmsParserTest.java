package in.yeedipay.bankrelay;

import org.junit.Test;
import static org.junit.Assert.*;

public class BankSmsParserTest {

    @Test
    public void parsesIciciCreditAfterVerb() {
        BankTransaction tx = BankSmsParser.parse(
                "VK-ICICIB",
                "A/c XX1234 credited with Rs.500.00. UPI Ref No 627032899714. ICICI Bank",
                1000L);
        assertNotNull(tx);
        assertEquals("ICICI", tx.bank);
        assertEquals("500.00", tx.amount.toPlainString());
        assertEquals("627032899714", tx.reference);
        assertEquals("1234", tx.accountLast4);
    }

    @Test
    public void parsesAmountBeforeReceivedAndDoesNotUseAccountLast4() {
        BankTransaction tx = BankSmsParser.parse(
                "VK-ICICIB",
                "Rs.250.00 received in A/c XX9999 via UPI. UTR 627032899714.",
                1000L);
        assertNotNull(tx);
        assertEquals("250.00", tx.amount.toPlainString());
        assertEquals("9999", tx.accountLast4);
    }

    @Test
    public void parsesIndusIndCredit() {
        BankTransaction tx = BankSmsParser.parse(
                "VM-INDUSB",
                "A/c XX3456 credited with INR 700.00 via UPI. UPI Ref No 627032899715.",
                1000L);
        assertNotNull(tx);
        assertEquals("INDUSIND", tx.bank);
        assertEquals("700.00", tx.amount.toPlainString());
        assertEquals("627032899715", tx.reference);
        assertEquals("3456", tx.accountLast4);
    }

    @Test
    public void parsesSlashUpiReference() {
        BankTransaction tx = BankSmsParser.parse(
                "AD-RBLBNK",
                "Your A/c XX2222 is credited with INR 1250.00 via UPI/627032899714/RAO.",
                1000L);
        assertNotNull(tx);
        assertEquals("RBL", tx.bank);
        assertEquals("1250.00", tx.amount.toPlainString());
        assertEquals("627032899714", tx.reference);
    }

    @Test
    public void parsesUpiTxnIdReference() {
        BankTransaction tx = BankSmsParser.parse(
                "VK-ICICIB",
                "A/c XX1234 credited by INR 99.00. UPI Txn ID 627032899714.",
                1000L);
        assertNotNull(tx);
        assertEquals("627032899714", tx.reference);
    }

    @Test
    public void ignoresActualOtpCode() {
        BankTransaction tx = BankSmsParser.parse(
                "VK-ICICIB",
                "OTP 123456 for transaction of Rs 500. Do not share your OTP.",
                1000L);
        assertNull(tx);
    }

    @Test
    public void creditWithSecurityFooterIsStillAccepted() {
        BankTransaction tx = BankSmsParser.parse(
                "VK-ICICIB",
                "A/c XX1234 credited with Rs.500.00. UPI Ref 627032899714. Never share OTP/PIN.",
                1000L);
        assertNotNull(tx);
        assertEquals("500.00", tx.amount.toPlainString());
    }

    @Test
    public void ignoresDebitOnly() {
        BankTransaction tx = BankSmsParser.parse(
                "AD-RBLBNK",
                "A/c XX2222 debited by INR 1000.00. Ref No 123456789012",
                1000L);
        assertNull(tx);
    }

    @Test
    public void ignoresDebitEvenWhenRequestReceivedAppears() {
        BankTransaction tx = BankSmsParser.parse(
                "AD-RBLBNK",
                "Your payment request received. A/c XX2222 debited by INR 1000.00. UPI Ref No 123456789012.",
                1000L);
        assertNull(tx);
    }

    @Test
    public void ignoresDebitEvenWhenReceivedAppearsAtEnd() {
        BankTransaction tx = BankSmsParser.parse(
                "AD-RBLBNK",
                "A/c XX2222 debited by INR 1000.00. Ref No 123456789012. Complaint request received.",
                1000L);
        assertNull(tx);
    }

    @Test
    public void ignoresUpiCollectRequestReceived() {
        BankTransaction tx = BankSmsParser.parse(
                "VK-ICICIB",
                "UPI collect request received for Rs.500.00 on A/c XX1234. UPI Ref No 627032899714.",
                1000L);
        assertNull(tx);
    }

    @Test
    public void ignoresRequestToPayReceived() {
        BankTransaction tx = BankSmsParser.parse(
                "VK-ICICIB",
                "You have received a request to pay Rs.500.00 from ABC. A/c XX1234. UPI Ref No 627032899714.",
                1000L);
        assertNull(tx);
    }

    @Test
    public void ignoresUnknownSender() {
        BankTransaction tx = BankSmsParser.parse(
                "UNKNOWN",
                "A/c XX1234 credited with Rs.500.00. UPI Ref No 627032899714. ICICI Bank",
                1000L);
        assertNull(tx);
    }
    @Test
    public void parsesPaymentReceivedPositive() {
        BankTransaction tx = BankSmsParser.parse(
                "VK-ICICIB",
                "Payment received via UPI in A/c XX1234 for Rs.75.00. UPI Ref No 627032899716.",
                1000L);
        assertNotNull(tx);
        assertEquals("75.00", tx.amount.toPlainString());
        assertEquals("627032899716", tx.reference);
    }

    @Test
    public void ignoresWillBeCredited() {
        assertNull(BankSmsParser.parse(
                "VK-ICICIB",
                "A/c XX1234 will be credited with Rs.500.00. UPI Ref No 627032899714.",
                1000L));
    }

    @Test
    public void ignoresRefundWillBeCredited() {
        assertNull(BankSmsParser.parse(
                "VK-ICICIB",
                "Refund of Rs.500.00 will be credited to A/c XX1234. UPI Ref No 627032899714.",
                1000L));
    }

    @Test
    public void ignoresCreditedBackFailedTransaction() {
        assertNull(BankSmsParser.parse(
                "VK-ICICIB",
                "A/c XX1234 credited back with Rs.500.00 due to failed UPI txn. UPI Ref No 627032899714.",
                1000L));
    }

    @Test
    public void ignoresPendingTransaction() {
        assertNull(BankSmsParser.parse(
                "VK-ICICIB",
                "UPI transaction pending. A/c XX1234 credited with Rs.500.00 after settlement. UPI Ref No 627032899714.",
                1000L));
    }

    @Test
    public void ignoresCashbackCredit() {
        assertNull(BankSmsParser.parse(
                "VK-ICICIB",
                "Cashback credited to A/c XX1234 for Rs.500.00 via UPI. UPI Ref No 627032899714.",
                1000L));
    }

    @Test
    public void ignoresNonUpiCredit() {
        assertNull(BankSmsParser.parse(
                "VK-ICICIB",
                "A/c XX1234 credited with Rs.500.00. NEFT Ref No ABCD1234567890.",
                1000L));
    }

    @Test
    public void rejectsUpiCreditWithoutReference() {
        BankTransaction tx = BankSmsParser.parse(
                "VK-ICICIB",
                "A/c XX1234 credited with Rs.500.00 via UPI.",
                1000L);
        assertNull(tx);
    }

    @Test
    public void parsesIciciCreditWithoutUpiWord() {
        BankTransaction tx = BankSmsParser.parse(
                "VA-ICICIT-S",
                "Your ICICI Bank Account 4270 has been credited with Rs. 1.00 on 2026-09-30 at 13:54:56 from BASI REDDY KARTHIK R. REF No 130465895146",
                1000L);
        assertNotNull(tx);
        assertEquals("ICICI", tx.bank);
        assertEquals("1.00", tx.amount.toPlainString());
        assertEquals("130465895146", tx.reference);
        assertEquals("4270", tx.accountLast4);
    }

    @Test
    public void rejectsUpiCreditWithoutAccountDigits() {
        BankTransaction tx = BankSmsParser.parse(
                "VM-INDUSB",
                "Your account is credited with INR 700.00 via UPI. UTR 627032899715.",
                1000L);
        assertNull(tx);
    }

}
