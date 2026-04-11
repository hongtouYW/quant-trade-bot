package com.dj.manager;

import android.app.Application;
import android.util.Log;

import com.dj.manager.util.SingletonUtil;
import com.google.firebase.messaging.FirebaseMessaging;

public class DJManagerApp extends Application {
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
    }
}
