package com.dj.user.util;

import android.content.ActivityNotFoundException;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.net.Uri;

public class GoogleAuthenticatorUtil {

    private static final String AUTHENTICATOR_PACKAGE = "com.google.android.apps.authenticator2";
    private static final String PLAY_STORE_URL = "https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2";

    public static void openGoogleAuthenticator(Context context) {
        PackageManager pm = context.getPackageManager();
        Intent launchIntent = pm.getLaunchIntentForPackage(AUTHENTICATOR_PACKAGE);

        if (launchIntent != null) {
            context.startActivity(launchIntent);
        } else {
            try {
                context.startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse("market://details?id=" + AUTHENTICATOR_PACKAGE)));
            } catch (ActivityNotFoundException e) {
                context.startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(PLAY_STORE_URL)));
            }
        }
    }
}
