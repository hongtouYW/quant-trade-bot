package com.dj.user.activity;

import android.content.Intent;
import android.os.Bundle;
import android.util.Log;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.biometric.BiometricPrompt;
import androidx.core.content.ContextCompat;

import com.dj.user.util.CacheManager;

public class LockActivity extends BaseActivity {
    private boolean isColdStart = false;
    private BiometricPrompt biometricPrompt;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        // Determine if this is cold start or from background
        if (getIntent().getAction() != null && getIntent().getAction().equals(Intent.ACTION_MAIN)) {
            // Launched as main activity - this is a cold start
            isColdStart = true;
            Log.d("LockActivity", "Cold start detected via ACTION_MAIN");
        } else {
            // Check the extra
            isColdStart = getIntent().getBooleanExtra("isColdStart", false);
            Log.d("LockActivity", "Background start, isColdStart: " + isColdStart);
        }

        boolean biometricEnabled = CacheManager.getBoolean(this, CacheManager.KEY_BIOMETRIC_ENABLED);
        Log.d("LockActivity", "Biometric enabled: " + biometricEnabled);

        if (!biometricEnabled) {
            // Skip lock if disabled
            Log.d("LockActivity", "Biometric disabled, skipping lock");
            unlockAndResume();
            return;
        }

        setupBiometricAuth();
    }

    private void setupBiometricAuth() {
        biometricPrompt = new BiometricPrompt(
                this,
                ContextCompat.getMainExecutor(this),
                new BiometricPrompt.AuthenticationCallback() {
                    @Override
                    public void onAuthenticationSucceeded(@NonNull BiometricPrompt.AuthenticationResult result) {
                        super.onAuthenticationSucceeded(result);
                        Log.d("LockActivity", "Biometric authentication succeeded");
                        unlockAndResume();
                    }

                    @Override
                    public void onAuthenticationError(int errorCode, @NonNull CharSequence errString) {
                        super.onAuthenticationError(errorCode, errString);
                        Log.e("LockActivity", "Biometric authentication error: " + errString);

                        if (errorCode == BiometricPrompt.ERROR_NEGATIVE_BUTTON) {
                            // User pressed negative button
                            finishAffinity(); // Exit app
                        } else if (errorCode == BiometricPrompt.ERROR_USER_CANCELED) {
                            // User canceled
                            finishAffinity(); // Exit app
                        } else {
                            // Other errors
                            Toast.makeText(LockActivity.this, "验证错误: " + errString, Toast.LENGTH_SHORT).show();
                            finishAffinity(); // Exit app on error
                        }
                    }

                    @Override
                    public void onAuthenticationFailed() {
                        super.onAuthenticationFailed();
                        Log.d("LockActivity", "Biometric authentication failed");
                        Toast.makeText(LockActivity.this, "验证失败", Toast.LENGTH_SHORT).show();
                    }
                }
        );

        BiometricPrompt.PromptInfo promptInfo = new BiometricPrompt.PromptInfo.Builder()
                .setTitle("验证身份")
                .setSubtitle("请使用生物识别解锁")
                .setNegativeButtonText("退出")
                .setConfirmationRequired(false)
                .build();

        Log.d("LockActivity", "Starting biometric authentication");
        biometricPrompt.authenticate(promptInfo);
    }

    private void unlockAndResume() {
        if (isTaskRoot()) {
            // Cold start → go to splash
            Intent intent = new Intent(this, SplashScreenActivity.class);
            intent.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TASK | Intent.FLAG_ACTIVITY_NEW_TASK);
            startActivity(intent);
        } else {
            // Resume → just close LockActivity
            finish();
        }
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
        Log.d("LockActivity", "LockActivity destroyed");
    }
}