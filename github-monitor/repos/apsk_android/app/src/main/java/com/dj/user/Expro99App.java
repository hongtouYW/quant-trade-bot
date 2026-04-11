package com.dj.user;

import android.app.Activity;
import android.app.Application;
import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Intent;
import android.os.Bundle;
import android.os.Handler;
import android.util.Log;

import com.dj.user.activity.LockActivity;
import com.dj.user.util.CacheManager;
import com.dj.user.util.SingletonUtil;
import com.dj.user.util.StringUtil;
import com.google.firebase.messaging.FirebaseMessaging;

public class Expro99App extends Application implements Application.ActivityLifecycleCallbacks {

    private int activityCount = 0;
    private boolean isInBackground = true;
    private boolean hasShownLockScreen = false;

    @Override
    public void onCreate() {
        super.onCreate();

        FirebaseMessaging.getInstance().setAutoInitEnabled(true);
        FirebaseMessaging.getInstance().getToken().addOnCompleteListener(task -> {
            if (!task.isSuccessful()) {
                Log.e("### ", "FCM getToken failed: " + task.getException());
                return;
            }
            String token = task.getResult();
            SingletonUtil.getInstance().setFcmToken(token);
            Log.d("### ", "FCM getToken onComplete: " + token);
        });

        registerActivityLifecycleCallbacks(this);
        Log.d("Expro99App", "Application created");
    }

    private void checkClipboard(Activity activity) {
        String token = CacheManager.getString(this, CacheManager.KEY_ACCESS_TOKEN);
        if (!StringUtil.isNullOrEmpty(token)) {
            return;
        }
        ClipboardManager clipboard = (ClipboardManager) getSystemService(CLIPBOARD_SERVICE);
        if (clipboard != null && clipboard.hasPrimaryClip()) {
            ClipData clipData = clipboard.getPrimaryClip();
            if (clipData != null && clipData.getItemCount() > 0) {
                CharSequence copiedText = clipData.getItemAt(0).coerceToText(this);
                if (copiedText != null) {
                    String copied = copiedText.toString().trim();
                    Log.d("Expro99App", "Clipboard text: " + copied);
                    // Expected format: ag[<agent-code>][<invitation-code>]
                    if (copied.startsWith("ag[")) {
                        int firstOpen = copied.indexOf("[");
                        int firstClose = copied.indexOf("]");
                        int secondOpen = copied.indexOf("[", firstClose);
                        int secondClose = copied.indexOf("]", secondOpen);

                        String agentCode = "";
                        String invitationCode = "";

                        if (firstOpen >= 0 && firstClose > firstOpen) {
                            agentCode = copied.substring(firstOpen + 1, firstClose);
                        }
                        if (secondOpen >= 0 && secondClose > secondOpen) {
                            invitationCode = copied.substring(secondOpen + 1, secondClose);
                        }

                        // Validate agent code (must be 32-char MD5)
                        boolean isValidMd5 = agentCode.matches("^[a-fA-F0-9]{32}$");
                        if (isValidMd5) {
                            CacheManager.saveString(activity, CacheManager.KEY_AGENT_CODE, agentCode);
                            Log.d("Expro99App", "Valid agent code cached: " + agentCode);
                            CacheManager.saveString(activity, CacheManager.KEY_SHOP_CODE, "");
                        } else {
                            Log.d("Expro99App", "Invalid agent code, skipping cache.");
                            CacheManager.saveString(activity, CacheManager.KEY_AGENT_CODE, "");
                        }
                        CacheManager.saveString(activity, CacheManager.KEY_INVITATION_CODE, invitationCode);
                        Log.d("Expro99App", "Invitation code cached: " + invitationCode);

                        // Clear clipboard to avoid reprocessing
                        clipboard.setPrimaryClip(ClipData.newPlainText("", ""));
                    }
                    if (copied.startsWith("shop[")) {
                        int firstOpen = copied.indexOf("[");
                        int firstClose = copied.indexOf("]");

                        String shopCode = "";
                        if (firstOpen >= 0 && firstClose > firstOpen) {
                            shopCode = copied.substring(firstOpen + 1, firstClose);
                        }
                        // Validate shop code (must be 32-char MD5)
                        boolean isValidMd5 = shopCode.matches("^[a-fA-F0-9]{32}$");
                        if (isValidMd5) {
                            CacheManager.saveString(activity, CacheManager.KEY_SHOP_CODE, shopCode);
                            Log.d("Expro99App", "Valid shop code cached: " + shopCode);
                            CacheManager.saveString(activity, CacheManager.KEY_AGENT_CODE, "");
                        } else {
                            Log.d("Expro99App", "Invalid shop code, skipping cache.");
                            CacheManager.saveString(activity, CacheManager.KEY_SHOP_CODE, "");
                        }
                        // Clear clipboard to avoid reprocessing
                        clipboard.setPrimaryClip(ClipData.newPlainText("", ""));
                    }
                }
            }
        }
    }

    @Override
    public void onActivityStarted(Activity activity) {
        activityCount++;
        Log.d("Expro99App", "Started: " + activity.getClass().getSimpleName() + ", count=" + activityCount);

        // First activity when coming to foreground
        if (isInBackground && activityCount == 1) {
            isInBackground = false;
            Log.d("Expro99App", "App came to foreground");

//            StringUtil.copyToClipboard(this, "", "Test");
            // 🔍 Check clipboard for ag-code
            new Handler(getMainLooper()).postDelayed(() -> checkClipboard(activity), 500);

            boolean biometricEnabled = CacheManager.getBoolean(activity, CacheManager.KEY_BIOMETRIC_ENABLED);
            if (biometricEnabled && !(activity instanceof LockActivity) && !hasShownLockScreen) {
                hasShownLockScreen = true;
                Intent intent = new Intent(activity, LockActivity.class);
                intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
                activity.startActivity(intent);
                Log.d("Expro99App", "LockActivity launched");
            }
        }
    }

    @Override
    public void onActivityStopped(Activity activity) {
        activityCount--;
        Log.d("Expro99App", "Stopped: " + activity.getClass().getSimpleName() + ", count=" + activityCount);

        if (activityCount == 0) {
            isInBackground = true;
            hasShownLockScreen = false; // reset for next time
            Log.d("Expro99App", "App went to background");
        }
    }

    // Other lifecycle methods are not needed unless you want extra logs
    @Override
    public void onActivityCreated(Activity activity, Bundle b) {
        new Handler(getMainLooper()).postDelayed(() -> checkClipboard(activity), 500);
    }

    @Override
    public void onActivityResumed(Activity a) {
    }

    @Override
    public void onActivityPaused(Activity a) {
    }

    @Override
    public void onActivitySaveInstanceState(Activity a, Bundle b) {
    }

    @Override
    public void onActivityDestroyed(Activity a) {
    }
}
