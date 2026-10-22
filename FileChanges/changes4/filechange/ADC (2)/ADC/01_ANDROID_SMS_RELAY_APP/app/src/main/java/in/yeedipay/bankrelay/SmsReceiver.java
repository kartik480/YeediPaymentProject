package in.yeedipay.bankrelay;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.provider.Telephony;
import android.telephony.SmsMessage;

public class SmsReceiver extends BroadcastReceiver {
    @Override
    public void onReceive(Context context, Intent intent) {
        if (!Telephony.Sms.Intents.SMS_RECEIVED_ACTION.equals(intent.getAction())) return;

        SmsMessage[] messages = Telephony.Sms.Intents.getMessagesFromIntent(intent);
        if (messages == null || messages.length == 0) return;

        String sender = messages[0].getOriginatingAddress();
        StringBuilder body = new StringBuilder();
        long ts = messages[0].getTimestampMillis();
        for (SmsMessage message : messages) {
            if (message.getMessageBody() != null) body.append(message.getMessageBody());
            ts = Math.max(ts, message.getTimestampMillis());
        }

        SecurePrefs.prefs(context).edit()
                .putString("last_sender", sender == null ? "unknown" : sender)
                .apply();

        BankTransaction tx = BankSmsParser.parse(sender, body.toString(), ts);
        if (tx == null) {
            SecurePrefs.prefs(context).edit()
                    .putString("last_status", "Ignored SMS from: " + (sender == null ? "unknown" : sender))
                    .apply();
            return;
        }

        String configured = SecurePrefs.prefs(context)
                .getString("last4_" + tx.bank.toLowerCase(), "");
        if (configured != null && !configured.trim().isEmpty()) {
            if (tx.accountLast4 == null || tx.accountLast4.trim().isEmpty()) {
                SecurePrefs.prefs(context).edit()
                        .putString("last_status", "Rejected " + tx.bank + " alert: account last4 not found")
                        .apply();
                return;
            }
            if (!containsLast4(configured, tx.accountLast4)) {
                SecurePrefs.prefs(context).edit()
                        .putString("last_status", "Rejected " + tx.bank + " alert: account mismatch")
                        .apply();
                return;
            }
        }

        RelayQueue.enqueue(context, tx);
        SecurePrefs.prefs(context).edit()
                .putString("last_status", "Queued " + tx.bank + " CREDIT ₹" + tx.amount + " Ref " + tx.reference)
                .apply();
    }

    private static boolean containsLast4(String csv, String last4) {
        for (String s : csv.split(",")) {
            if (last4.equals(s.trim())) return true;
        }
        return false;
    }
}
