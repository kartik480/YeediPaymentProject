package in.yeedipay.bankrelay;

import android.content.Context;

import androidx.work.BackoffPolicy;
import androidx.work.Constraints;
import androidx.work.Data;
import androidx.work.ExistingPeriodicWorkPolicy;
import androidx.work.NetworkType;
import androidx.work.OneTimeWorkRequest;
import androidx.work.PeriodicWorkRequest;
import androidx.work.WorkManager;

import java.util.UUID;
import java.util.concurrent.TimeUnit;

public final class RelayQueue {
    private RelayQueue() {}

    public static void enqueue(Context context, BankTransaction tx) {
        Data data = new Data.Builder()
                .putString("bank", tx.bank)
                .putString("amount", tx.amount.toPlainString())
                .putString("reference", tx.reference)
                .putString("account_last4", tx.accountLast4 == null ? "" : tx.accountLast4)
                .putString("sender", tx.sender)
                .putLong("received_at_ms", tx.receivedAtMillis)
                .putBoolean("is_test", false)
                .build();
        enqueueData(context, data);
    }

    public static void enqueueTest(Context context) {
        String ref = "TEST" + UUID.randomUUID().toString().replace("-", "").substring(0, 12).toUpperCase();
        Data data = new Data.Builder()
                .putString("bank", "ICICI")
                .putString("amount", "1.23")
                .putString("reference", ref)
                .putString("account_last4", "")
                .putString("sender", "TEST-ICICI")
                .putLong("received_at_ms", System.currentTimeMillis())
                .putBoolean("is_test", true)
                .build();
        enqueueData(context, data);
    }


    public static void scheduleHeartbeat(Context context) {
        Data data = new Data.Builder()
                .putString("bank", "ICICI")
                .putString("amount", "0.01")
                .putString("reference", "HEARTBEAT000001")
                .putString("account_last4", "")
                .putString("sender", "YEEDI-HEARTBEAT")
                .putLong("received_at_ms", System.currentTimeMillis())
                .putBoolean("is_test", true)
                .build();

        Constraints constraints = new Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .build();

        PeriodicWorkRequest request = new PeriodicWorkRequest.Builder(
                UploadWorker.class, 6, TimeUnit.HOURS)
                .setInputData(data)
                .setConstraints(constraints)
                .build();

        WorkManager.getInstance(context).enqueueUniquePeriodicWork(
                "yeedipay-bank-relay-heartbeat",
                ExistingPeriodicWorkPolicy.UPDATE,
                request);
    }

    private static void enqueueData(Context context, Data data) {
        Constraints constraints = new Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .build();

        OneTimeWorkRequest request = new OneTimeWorkRequest.Builder(UploadWorker.class)
                .setInputData(data)
                .setConstraints(constraints)
                .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 15, TimeUnit.SECONDS)
                .build();

        WorkManager.getInstance(context).enqueue(request);
    }
}
