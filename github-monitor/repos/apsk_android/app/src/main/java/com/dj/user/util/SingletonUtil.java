package com.dj.user.util;

public class SingletonUtil {

    private String fcmToken;
    private static SingletonUtil singletonUtil;

    public static SingletonUtil getInstance() {
        if (singletonUtil == null) {
            singletonUtil = new SingletonUtil();
        }
        return singletonUtil;
    }

    public void clearResetSingletonData() {
    }

    public String getFcmToken() {
        return fcmToken != null ? fcmToken : "";
    }

    public void setFcmToken(String fcmToken) {
        this.fcmToken = fcmToken;
    }
}