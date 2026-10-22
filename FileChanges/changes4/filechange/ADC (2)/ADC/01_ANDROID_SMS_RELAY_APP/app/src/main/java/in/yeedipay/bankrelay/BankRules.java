package in.yeedipay.bankrelay;

import java.util.Locale;

public final class BankRules {
    private BankRules() {}

    public static String identifyBankFromSender(String sender) {
        if (sender == null) return null;
        String s = sender.toUpperCase(Locale.ROOT).replace(" ", "");
        if (s.contains("ICICI")) return "ICICI";
        if (s.contains("INDUS")) return "INDUSIND";
        if (s.contains("RBL")) return "RBL";
        return null;
    }
}
