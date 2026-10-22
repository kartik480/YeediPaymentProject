package in.yeedipay.bankrelay;

import android.content.Context;
import android.content.SharedPreferences;

import androidx.annotation.NonNull;
import androidx.work.Worker;
import androidx.work.WorkerParameters;

import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.math.BigDecimal;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.Locale;
import java.util.TimeZone;
import java.util.UUID;

import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;

public class UploadWorker extends Worker {
    public UploadWorker(@NonNull Context appContext, @NonNull WorkerParameters workerParams) {
        super(appContext, workerParams);
    }

    @NonNull
    @Override
    public Result doWork() {
        Context context = getApplicationContext();
        // Do not impose a fixed retry-count ceiling here. WorkManager constraints/backoff
        // handle transient network/server outages; the server remains the authority for
        // rejecting events that are genuinely too old (max_event_age_hours).
        SharedPreferences prefs = SecurePrefs.prefs(context);
        String endpoint = prefs.getString("endpoint", "");
        String deviceId = prefs.getString("device_id", "");
        String secret = SecurePrefs.getSecret(context);

        if (endpoint == null || !endpoint.startsWith("https://")
                || deviceId == null || deviceId.isEmpty()
                || secret == null || secret.isEmpty()) {
            setStatus(prefs, "Upload blocked: configure endpoint, Device ID and Secret");
            return Result.failure();
        }

        try {
            String bank = getInputData().getString("bank");
            String amountText = getInputData().getString("amount");
            String reference = getInputData().getString("reference");
            String sender = getInputData().getString("sender");
            String accountLast4 = getInputData().getString("account_last4");
            long receivedAtMs = getInputData().getLong("received_at_ms", System.currentTimeMillis());
            boolean isTest = getInputData().getBoolean("is_test", false);

            // Re-validate input before network transmission.
            BigDecimal amount = new BigDecimal(amountText).setScale(2, java.math.RoundingMode.HALF_UP);
            if (bank == null || bank.trim().isEmpty()
                    || reference == null || reference.trim().isEmpty()
                    || accountLast4 == null || !accountLast4.matches("\\d{4}")
                    || sender == null || sender.trim().isEmpty()
                    || amount.signum() <= 0) {
                setStatus(prefs, "Upload blocked: bank, amount, account last-4 and UTR/RRN are required");
                return Result.failure();
            }

            String eventId = sha256Hex(bank + "|" + sender + "|" + reference + "|" + amount.toPlainString() + "|" + receivedAtMs);

            JSONObject json = new JSONObject();
            json.put("event_id", eventId);
            json.put("bank", bank);
            json.put("channel", "UPI");
            json.put("amount", amount.toPlainString());
            json.put("reference", reference);
            json.put("account_last4", accountLast4 == null ? "" : accountLast4);
            json.put("sender", sender);
            json.put("received_at", isoUtc(receivedAtMs));
            json.put("is_test", isTest);
            // Raw SMS body is intentionally never included.

            byte[] body = json.toString().getBytes(StandardCharsets.UTF_8);
            long requestTs = System.currentTimeMillis() / 1000L;
            String nonce = UUID.randomUUID().toString().replace("-", "");
            String signature = hmacSha256Hex(secret, requestTs + "\n" + nonce + "\n" + new String(body, StandardCharsets.UTF_8));

            HttpURLConnection conn = (HttpURLConnection) new URL(endpoint).openConnection();
            conn.setRequestMethod("POST");
            conn.setConnectTimeout(15000);
            conn.setReadTimeout(15000);
            conn.setDoOutput(true);
            conn.setRequestProperty("Content-Type", "application/json; charset=utf-8");
            conn.setRequestProperty("X-Device-Id", deviceId);
            conn.setRequestProperty("X-Timestamp", String.valueOf(requestTs));
            conn.setRequestProperty("X-Nonce", nonce);
            conn.setRequestProperty("X-Signature", signature);

            try (OutputStream os = conn.getOutputStream()) {
                os.write(body);
            }

            int code = conn.getResponseCode();
            String response = readResponse(conn, code);
            if (code >= 200 && code < 300) {
                if (isTest) {
                    setStatus(prefs, "Relay heartbeat / connection test OK");
                } else {
                    setStatus(prefs, "Uploaded " + bank + " ₹" + amount + " Ref " + reference);
                }
                return Result.success();
            }
            if (code == 408 || code == 425 || code == 429 || code >= 500) {
                setStatus(prefs, "Temporary server error " + code + "; retry scheduled");
                return Result.retry();
            }
            setStatus(prefs, "Server rejected " + bank + " sender " + sender + ": HTTP " + code + " " + response);
            return Result.failure();
        } catch (java.net.SocketTimeoutException | java.net.UnknownHostException e) {
            return Result.retry();
        } catch (Exception e) {
            setStatus(prefs, "Upload error: " + e.getClass().getSimpleName());
            return Result.retry();
        }
    }

    private static String readResponse(HttpURLConnection conn, int code) {
        try (BufferedReader br = new BufferedReader(new InputStreamReader(
                code >= 400 ? conn.getErrorStream() : conn.getInputStream(), StandardCharsets.UTF_8))) {
            StringBuilder sb = new StringBuilder();
            String line;
            while ((line = br.readLine()) != null && sb.length() < 1000) sb.append(line);
            return sb.toString();
        } catch (Exception e) {
            return "";
        }
    }

    private static String isoUtc(long millis) {
        SimpleDateFormat f = new SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss.SSS'Z'", Locale.US);
        f.setTimeZone(TimeZone.getTimeZone("UTC"));
        return f.format(new Date(millis));
    }

    private static String sha256Hex(String s) throws Exception {
        MessageDigest digest = MessageDigest.getInstance("SHA-256");
        return toHex(digest.digest(s.getBytes(StandardCharsets.UTF_8)));
    }

    private static String hmacSha256Hex(String secret, String message) throws Exception {
        Mac mac = Mac.getInstance("HmacSHA256");
        mac.init(new SecretKeySpec(secret.getBytes(StandardCharsets.UTF_8), "HmacSHA256"));
        return toHex(mac.doFinal(message.getBytes(StandardCharsets.UTF_8)));
    }

    private static String toHex(byte[] bytes) {
        StringBuilder sb = new StringBuilder(bytes.length * 2);
        for (byte b : bytes) sb.append(String.format(Locale.US, "%02x", b & 0xff));
        return sb.toString();
    }

    private static void setStatus(SharedPreferences prefs, String status) {
        prefs.edit().putString("last_status", status).apply();
    }
}
