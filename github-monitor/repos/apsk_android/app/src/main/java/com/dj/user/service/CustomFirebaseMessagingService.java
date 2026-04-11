package com.dj.user.service;

import android.content.Intent;
import android.util.Log;

import androidx.annotation.NonNull;

import com.dj.user.R;
import com.dj.user.activity.SplashScreenActivity;
import com.dj.user.notify.Notify;
import com.dj.user.util.SingletonUtil;
import com.dj.user.util.StringUtil;
import com.google.firebase.messaging.FirebaseMessagingService;
import com.google.firebase.messaging.RemoteMessage;

import java.util.Map;

public class CustomFirebaseMessagingService extends FirebaseMessagingService {

    @Override
    public void onNewToken(@NonNull String token) {
        super.onNewToken(token);
        SingletonUtil.getInstance().setFcmToken(token);
        Log.d("### ", "onNewToken: " + token);
//        registerDevice(token);
    }

    @Override
    public void onMessageReceived(RemoteMessage remoteMessage) {
        String title = getString(R.string.app_name);
        String body = getString(R.string.new_notification);
        // DATA payload (preferred)
        if (remoteMessage.getData() != null && !remoteMessage.getData().isEmpty()) {
            Map<String, String> data = remoteMessage.getData();
            if (!StringUtil.isNullOrEmpty(data.get("title"))) {
                title = data.get("title");
            }
            if (!StringUtil.isNullOrEmpty(data.get("body"))) {
                body = data.get("body");
            }
        }
        // NOTIFICATION payload (fallback)
        if (remoteMessage.getNotification() != null) {
            if (!StringUtil.isNullOrEmpty(remoteMessage.getNotification().getTitle())) {
                title = remoteMessage.getNotification().getTitle();
            }
            if (!StringUtil.isNullOrEmpty(remoteMessage.getNotification().getBody())) {
                body = remoteMessage.getNotification().getBody();
            }
        }
        Intent intent = new Intent(this, SplashScreenActivity.class);
        intent.setFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP | Intent.FLAG_ACTIVITY_CLEAR_TOP);
        Notify.build(getApplicationContext())
                .setTitle(title)
                .setContent(body)
                .setSmallIcon(R.drawable.ic_stat_ic_notification)
                .setColor(R.color.orange_F8AF07)
                .setAction(intent)
                .setImportance(Notify.NotifyImportance.MAX)
                .setAutoCancel(true)
                .show();
    }

    @Override
    public void onDeletedMessages() {
        super.onDeletedMessages();
    }

    public static class Notification {
        private static Notification instance;

        public static Notification getInstance() {
            if (instance == null) {
                instance = new Notification();
            }
            return instance;
        }

    }
}
