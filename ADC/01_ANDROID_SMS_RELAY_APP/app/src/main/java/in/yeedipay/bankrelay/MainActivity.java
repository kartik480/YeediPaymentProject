package in.yeedipay.bankrelay;

import android.Manifest;
import android.content.pm.PackageManager;
import android.os.Bundle;
import android.text.TextUtils;
import android.widget.Button;
import android.widget.EditText;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;

public class MainActivity extends AppCompatActivity {
    private static final int SMS_PERMISSION_REQUEST = 2001;

    private EditText endpointInput;
    private EditText deviceIdInput;
    private EditText secretInput;
    private EditText iciciLast4Input;
    private EditText indusindLast4Input;
    private EditText rblLast4Input;
    private TextView statusText;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        endpointInput = findViewById(R.id.endpointInput);
        deviceIdInput = findViewById(R.id.deviceIdInput);
        secretInput = findViewById(R.id.secretInput);
        iciciLast4Input = findViewById(R.id.iciciLast4Input);
        indusindLast4Input = findViewById(R.id.indusindLast4Input);
        rblLast4Input = findViewById(R.id.rblLast4Input);
        statusText = findViewById(R.id.statusText);
        Button saveButton = findViewById(R.id.saveButton);
        Button permissionButton = findViewById(R.id.permissionButton);
        Button testButton = findViewById(R.id.testButton);

        loadConfig();
        refreshStatus();
        if (SecurePrefs.getSecret(this) != null
                && !SecurePrefs.prefs(this).getString("endpoint", "").isEmpty()) {
            RelayQueue.scheduleHeartbeat(this);
        }

        saveButton.setOnClickListener(v -> {
            if (saveConfig()) RelayQueue.scheduleHeartbeat(this);
        });
        permissionButton.setOnClickListener(v -> requestSmsPermission());
        testButton.setOnClickListener(v -> {
            if (saveConfig()) {
                RelayQueue.enqueueTest(this);
                Toast.makeText(this, "Safe test event queued", Toast.LENGTH_SHORT).show();
                refreshStatus();
            }
        });
    }

    @Override
    protected void onResume() {
        super.onResume();
        refreshStatus();
    }

    private void loadConfig() {
        endpointInput.setText(SecurePrefs.prefs(this).getString("endpoint", ""));
        deviceIdInput.setText(SecurePrefs.prefs(this).getString("device_id", "YEEDI-SMS-01"));
        iciciLast4Input.setText(SecurePrefs.prefs(this).getString("last4_icici", ""));
        indusindLast4Input.setText(SecurePrefs.prefs(this).getString("last4_indusind", ""));
        rblLast4Input.setText(SecurePrefs.prefs(this).getString("last4_rbl", ""));
    }

    private boolean saveConfig() {
        String endpoint = endpointInput.getText().toString().trim();
        String deviceId = deviceIdInput.getText().toString().trim();
        String secret = secretInput.getText().toString().trim();

        if (!endpoint.startsWith("https://") && !endpoint.startsWith("http://")) {
            toast("Endpoint must start with http:// or https://");
            return false;
        }
        if (TextUtils.isEmpty(deviceId)) {
            toast("Device ID is required");
            return false;
        }
        if (TextUtils.isEmpty(secret) && TextUtils.isEmpty(SecurePrefs.getSecret(this))) {
            toast("Device API Secret is required the first time");
            return false;
        }

        SecurePrefs.prefs(this).edit()
                .putString("endpoint", endpoint)
                .putString("device_id", deviceId)
                .putString("last4_icici", sanitizeLast4List(iciciLast4Input.getText().toString()))
                .putString("last4_indusind", sanitizeLast4List(indusindLast4Input.getText().toString()))
                .putString("last4_rbl", sanitizeLast4List(rblLast4Input.getText().toString()))
                .apply();

        if (!TextUtils.isEmpty(secret)) {
            try {
                SecurePrefs.saveSecret(this, secret);
                secretInput.setText("");
            } catch (Exception e) {
                toast("Could not securely save secret");
                return false;
            }
        }
        toast("Configuration saved");
        refreshStatus();
        return true;
    }

    private String sanitizeLast4List(String value) {
        if (value == null || value.trim().isEmpty()) return "";
        String[] parts = value.split(",");
        StringBuilder out = new StringBuilder();
        for (String p : parts) {
            String digits = p.replaceAll("[^0-9]", "");
            if (digits.length() != 4) continue;
            if (out.length() > 0) out.append(",");
            out.append(digits);
        }
        return out.toString();
    }

    private void requestSmsPermission() {
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.RECEIVE_SMS)
                == PackageManager.PERMISSION_GRANTED) {
            toast("Bank SMS access is already allowed");
            return;
        }
        ActivityCompat.requestPermissions(this,
                new String[]{Manifest.permission.RECEIVE_SMS}, SMS_PERMISSION_REQUEST);
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, String[] permissions, int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        if (requestCode == SMS_PERMISSION_REQUEST) {
            if (grantResults.length > 0 && grantResults[0] == PackageManager.PERMISSION_GRANTED) {
                toast("Bank SMS access allowed");
            } else {
                toast("SMS permission not granted. Relay cannot receive bank alerts.");
            }
            refreshStatus();
        }
    }

    private void refreshStatus() {
        boolean permission = ContextCompat.checkSelfPermission(this, Manifest.permission.RECEIVE_SMS)
                == PackageManager.PERMISSION_GRANTED;
        String last = SecurePrefs.prefs(this).getString("last_status", "No transaction event yet");
        String configured = SecurePrefs.getSecret(this) != null ? "configured" : "missing";
        String lastSender = SecurePrefs.prefs(this).getString("last_sender", "none yet");
        statusText.setText("SMS permission: " + (permission ? "ALLOWED" : "NOT ALLOWED")
                + "\nAPI secret: " + configured
                + "\nLast SMS sender: " + lastSender
                + "\nLast event: " + last);
    }

    private void toast(String message) {
        Toast.makeText(this, message, Toast.LENGTH_SHORT).show();
    }
}
