package com.dj.user.util;

import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Bitmap;
import android.net.Uri;
import android.util.Log;
import android.widget.Toast;

public class SocialMediaUtil {

    // Check if an app is installed
    public static boolean isAppInstalled(Context context, String packageName) {
        try {
            context.getPackageManager().getPackageInfo(packageName, 0);
            return true;
        } catch (PackageManager.NameNotFoundException e) {
            return false;
        }
    }

    public static void shareToWeChat(Context context, Bitmap bitmap, String text) {
        String packageName = "com.tencent.mm";
        if (!isAppInstalled(context, packageName)) {
            Toast.makeText(context, "WeChat is not installed", Toast.LENGTH_SHORT).show();
            return;
        }
        try {
            Uri uri = ImageUtils.saveBitmap(context, bitmap, "share_temp");
            if (uri == null) {
                Toast.makeText(context, "Failed to save image", Toast.LENGTH_SHORT).show();
                return;
            }
            Intent shareIntent = new Intent(Intent.ACTION_SEND);
            shareIntent.setType("image/*");
            shareIntent.putExtra(Intent.EXTRA_STREAM, uri);
            if (text != null && !text.isEmpty()) {
                shareIntent.putExtra(Intent.EXTRA_TEXT, text);
            }
            shareIntent.setPackage(packageName);
            shareIntent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);
            context.startActivity(shareIntent);
        } catch (Exception e) {
            Log.e("###", "shareToWeChat: ", e);
            Toast.makeText(context, "Unable to share via WeChat", Toast.LENGTH_SHORT).show();
        }
    }

    // Share image with optional text to WhatsApp
    public static void shareToWhatsApp(Context context, Bitmap bitmap, String text) {
        String packageName = "com.whatsapp";
        if (!isAppInstalled(context, packageName)) {
            Toast.makeText(context, "WhatsApp is not installed", Toast.LENGTH_SHORT).show();
            return;
        }
        try {
            Uri uri = ImageUtils.saveBitmap(context, bitmap, "share_temp");
            if (uri == null) {
                Toast.makeText(context, "Failed to save image", Toast.LENGTH_SHORT).show();
                return;
            }
            Intent shareIntent = new Intent(Intent.ACTION_SEND);
            shareIntent.setType("image/*");
            shareIntent.putExtra(Intent.EXTRA_STREAM, uri);
            if (text != null && !text.isEmpty()) {
                shareIntent.putExtra(Intent.EXTRA_TEXT, text);
            }
            shareIntent.setPackage(packageName);
            shareIntent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);
            context.startActivity(shareIntent);
        } catch (Exception e) {
            Log.e("###", "shareToWhatsApp: ", e);
            Toast.makeText(context, "Unable to share via WhatsApp", Toast.LENGTH_SHORT).show();
        }
    }

    // Share image to Facebook (text must be typed manually)
    public static void shareToFacebook(Context context, Bitmap bitmap, String text) {
        String packageName = "com.facebook.katana";
        if (!isAppInstalled(context, packageName)) {
            Toast.makeText(context, "Facebook is not installed", Toast.LENGTH_SHORT).show();
            return;
        }
        try {
            Uri uri = ImageUtils.saveBitmap(context, bitmap, "share_temp");
            if (uri == null) {
                Toast.makeText(context, "Failed to save image", Toast.LENGTH_SHORT).show();
                return;
            }
            Intent shareIntent = new Intent(Intent.ACTION_SEND);
            shareIntent.setType("image/*");
            shareIntent.putExtra(Intent.EXTRA_STREAM, uri);
            if (text != null && !text.isEmpty()) {
                shareIntent.putExtra(Intent.EXTRA_TEXT, text);
            }
            shareIntent.setPackage(packageName);
            shareIntent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);
            context.startActivity(shareIntent);
        } catch (Exception e) {
            Log.e("###", "shareToFacebook: ", e);
            Toast.makeText(context, "Unable to share via Facebook", Toast.LENGTH_SHORT).show();
        }
    }

    // Share image with text to Telegram
    public static void shareToTelegram(Context context, Bitmap bitmap, String text) {
        String packageName = "org.telegram.messenger";
        if (!isAppInstalled(context, packageName)) {
            Toast.makeText(context, "Telegram is not installed", Toast.LENGTH_SHORT).show();
            return;
        }
        try {
            Uri uri = ImageUtils.saveBitmap(context, bitmap, "share_temp");
            if (uri == null) {
                Toast.makeText(context, "Failed to save image", Toast.LENGTH_SHORT).show();
                return;
            }
            Intent shareIntent = new Intent(Intent.ACTION_SEND);
            shareIntent.setType("image/*");
            shareIntent.putExtra(Intent.EXTRA_STREAM, uri);
            if (text != null && !text.isEmpty()) {
                shareIntent.putExtra(Intent.EXTRA_TEXT, text);
            }
            shareIntent.setPackage(packageName);
            shareIntent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);
            context.startActivity(shareIntent);
        } catch (Exception e) {
            Log.e("###", "shareToTelegram: ", e);
            Toast.makeText(context, "Unable to share via Telegram", Toast.LENGTH_SHORT).show();
        }
    }

    // Generic chooser: share image + optional text
    public static void shareGeneric(Context context, Bitmap bitmap, String text) {
        try {
            Uri uri = ImageUtils.saveBitmap(context, bitmap, "share_temp");
            if (uri == null) {
                Toast.makeText(context, "Failed to save image", Toast.LENGTH_SHORT).show();
                return;
            }

            Intent shareIntent = new Intent(Intent.ACTION_SEND);
            shareIntent.setType("image/*");
            shareIntent.putExtra(Intent.EXTRA_STREAM, uri);
            if (text != null && !text.isEmpty()) {
                shareIntent.putExtra(Intent.EXTRA_TEXT, text);
            }
            shareIntent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);

            context.startActivity(Intent.createChooser(shareIntent, "Share via"));
        } catch (Exception e) {
            Log.e("###", "shareGeneric: ", e);
            Toast.makeText(context, "Unable to share content", Toast.LENGTH_SHORT).show();
        }
    }
}
