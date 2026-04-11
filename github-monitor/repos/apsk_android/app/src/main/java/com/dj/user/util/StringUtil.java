package com.dj.user.util;

import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Context;
import android.text.TextUtils;
import android.util.Patterns;
import android.widget.Toast;

public class StringUtil {
    public static void copyToClipboard(Context context, String label, String text) {
        ClipboardManager clipboard = (ClipboardManager) context.getSystemService(Context.CLIPBOARD_SERVICE);
        ClipData clip = ClipData.newPlainText(label, text);
        clipboard.setPrimaryClip(clip);
        Toast.makeText(context, "Copied to clipboard", Toast.LENGTH_SHORT).show();
    }

    public static boolean isNullOrEmpty(String str) {
        return str == null || str.isEmpty();
    }

    public static boolean isValidAlphanumeric(String input) {
        // Regex: must contain at least one uppercase, one lowercase, one digit, only alphanumeric, length 6–16
//        return input != null && input.matches("^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)[A-Za-z\\d]{6,16}$");
        // Regex: must contain at least one lowercase, one digit, only alphanumeric, length 6–16
//        return input != null && input.matches("^(?=.*[a-z])(?=.*\\d)[A-Za-z\\d]{6,16}$");
        return input != null && input.length() >= 6 && input.length() <= 16;
    }

    public static boolean isValidPhone(String str) {
        return str.matches("^60[1-9][0-9]{8,9}$");
    }
    public static boolean isValidEmail(String str) {
        return (!TextUtils.isEmpty(str) && Patterns.EMAIL_ADDRESS.matcher(str).matches());
    }
}
