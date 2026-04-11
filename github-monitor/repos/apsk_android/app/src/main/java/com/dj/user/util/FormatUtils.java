package com.dj.user.util;

import android.content.Context;
import android.util.DisplayMetrics;
import android.util.Log;
import android.view.WindowManager;
import android.widget.EditText;

import java.text.DecimalFormat;

public class FormatUtils {

    public static int dpToPx(Context context, int dp) {
        return (int) (dp * context.getResources().getDisplayMetrics().density);
    }

    public static int getDeviceWidth(Context context) {
        DisplayMetrics displayMetrics = new DisplayMetrics();
        WindowManager windowManager = (WindowManager) context.getSystemService(Context.WINDOW_SERVICE);
        if (windowManager != null) {
            windowManager.getDefaultDisplay().getMetrics(displayMetrics);
        }
        return displayMetrics.widthPixels;
    }

    public static double getEditTextAmount(EditText editText) {
        String text = editText.getText().toString();
        if (text.isEmpty()) {
            return 0.00;
        }
        text = text.replace(",", "").trim();
        try {
            return Double.parseDouble(text);
        } catch (NumberFormatException e) {
            Log.e("###", "getEditTextAmount: ", e);
            return 0.0;
        }
    }

    public static String formatInteger(int amount) {
        DecimalFormat formatter = new DecimalFormat("#,##0");
        return formatter.format(amount);
    }

    public static String formatAmount(double amount) {
        DecimalFormat formatter = new DecimalFormat("#,##0.00");
        return formatter.format(amount);
    }

    public static String maskWithDots(String input) {
        if (input == null || input.length() <= 4) return input;
        int maskedLength = input.length() - 4;
        StringBuilder masked = new StringBuilder();
        for (int i = 0; i < maskedLength; i++) {
            masked.append("•");
            if ((i + 1) % 4 == 0 && i + 1 < maskedLength) {
                masked.append(" ");
            }
        }
        if (maskedLength % 4 == 0) {
            masked.append(" ");
        }
        String last4 = input.substring(input.length() - 4);
        for (int i = 0; i < last4.length(); i++) {
            masked.append(last4.charAt(i));
            if ((i + 1) % 4 == 0 && i + 1 < last4.length()) {
                masked.append(" ");
            }
        }
        return masked.toString();
    }

    public static String formatMsianPhone(String rawNumber) {
        if (rawNumber == null) return "";
        // If contains alphabet, return raw immediately
        if (rawNumber.matches(".*[a-zA-Z].*")) {
            return rawNumber;
        }
        // Normalize
        rawNumber = rawNumber.replaceAll("\\s+", "");
        // Ensure starts with +
        if (!rawNumber.startsWith("+")) {
            rawNumber = "+" + rawNumber;
        }
        // Remove + for processing
        String digits = rawNumber.substring(1);
        // Special case: country code 111
        if (digits.startsWith("111")) {
            // +111 1111 1111
            return formatWithPattern("+", digits, new int[]{4, 4, 4, 4});
        }
        // Malaysia numbers
        if (digits.startsWith("60")) {
            // +60 11 1111 1111
            return formatWithPattern("+", digits, new int[]{2, 2, 4, 4, 4});
        }
        // Fallback – just return normalized
        return rawNumber;
    }

    private static String formatWithPattern(String prefix, String digits, int[] groups) {
        StringBuilder sb = new StringBuilder(prefix);
        int index = 0;
        for (int group : groups) {
            if (index >= digits.length()) break;
            int end = Math.min(index + group, digits.length());
            sb.append(digits, index, end);
            index = end;
            if (index < digits.length()) {
                sb.append(" ");
            }
        }
        return sb.toString();
    }

    public static String maskPhoneNumberLast4(String rawNumber) {
        if (rawNumber == null) return "";
        String digitsOnly = rawNumber.replaceAll("\\D+", "");
        if (digitsOnly.length() < 4) return rawNumber;
        String last4 = digitsOnly.substring(digitsOnly.length() - 4);
        return "**** **** " + last4;
    }

    public static String maskPhoneNumber(String rawNumber) {
        if (rawNumber == null || rawNumber.isEmpty()) return "";
        String normalized = rawNumber.replaceAll("[^0-9+]", "");
        String countryCode = "";
        String digitsOnly = normalized;

        if (normalized.startsWith("+")) {
            int endIndex = Math.min(4, normalized.length());
            countryCode = normalized.substring(0, endIndex).replaceAll("[^0-9+]", "");
            digitsOnly = normalized.substring(countryCode.length());
        }
        if (digitsOnly.length() <= 4) {
            return countryCode + " " + digitsOnly;
        }
        String last4 = digitsOnly.substring(digitsOnly.length() - 4);
        StringBuilder masked = new StringBuilder();
        for (int i = 0; i < digitsOnly.length() - 4; i++) {
            masked.append("*");
        }
        String maskedSection = masked + last4;
        String grouped = maskedSection.replaceAll("(.{4})(?=.)", "$1 ");
        return countryCode + " " + grouped.trim();
    }

    public static String maskEmail(String email) {
        if (email == null || !email.contains("@")) return email;
        String[] parts = email.split("@");
        String local = parts[0];
        String domain = parts[1];

        if (local.length() <= 3) {
            local = local.charAt(0) + "*".repeat(local.length() - 1);
        } else {
            int maskLength = local.length() - 3;
            String masked = local.charAt(0) + "*".repeat(maskLength) + local.substring(local.length() - 2);
            local = masked;
        }
        return local + "@" + domain;
    }
}
