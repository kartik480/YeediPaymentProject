package in.yeedipay.bankrelay;

import java.math.BigDecimal;

public class BankTransaction {
    public final String bank;
    public final BigDecimal amount;
    public final String reference;
    public final String accountLast4;
    public final String sender;
    public final long receivedAtMillis;

    public BankTransaction(String bank, BigDecimal amount, String reference,
                           String accountLast4, String sender, long receivedAtMillis) {
        this.bank = bank;
        this.amount = amount;
        this.reference = reference;
        this.accountLast4 = accountLast4;
        this.sender = sender;
        this.receivedAtMillis = receivedAtMillis;
    }
}
