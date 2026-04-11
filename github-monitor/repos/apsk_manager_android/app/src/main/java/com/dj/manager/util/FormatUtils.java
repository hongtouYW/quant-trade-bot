package com.dj.manager.util;

import android.content.Context;

import java.text.DecimalFormat;

public class FormatUtils {

    public static int dpToPx(Context context, int dp) {
        return (int) (dp * context.getResources().getDisplayMetrics().density);
    }

    public static String formatInteger(int amount) {
        DecimalFormat formatter = new DecimalFormat("#,##0");
        return formatter.format(amount);
    }

    public static String formatAmount(double amount) {
        DecimalFormat formatter = new DecimalFormat("#,##0.00");
        return formatter.format(amount);
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
}
