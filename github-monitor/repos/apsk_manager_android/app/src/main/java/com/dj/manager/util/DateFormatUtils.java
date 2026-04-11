package com.dj.manager.util;

import android.content.Context;

import com.dj.manager.R;

import java.text.ParseException;
import java.text.SimpleDateFormat;
import java.util.Arrays;
import java.util.Date;
import java.util.List;
import java.util.Locale;
import java.util.TimeZone;

public class DateFormatUtils {
    public static final String FORMAT_HH_MM_A = "hh:mma";
    public static final String FORMAT_DD_MM_YYYY = "dd/MM/yyyy";
    public static final String FORMAT_YYYY_MM_DD_DASHED = "yyyy-MM-dd";
    public static final String FORMAT_YYYY_MM_DD = "yyyy MM.dd";
    public static final String FORMAT_YYYY_MM_DD_HH_MM_A = "yyyy MM.dd, hh:mma";
    public static final String FORMAT_DD_MMMM_YYYY_HH_MM_A = "dd MMMM yyyy hh:mma";

    public static String formatIsoDate(String input, String outputPattern) {
        if (StringUtil.isNullOrEmpty(input)) {
            return "-";
        }
        List<String> possibleFormats = Arrays.asList(
                "yyyy-MM-dd'T'HH:mm:ss.SSSSSS'Z'",
                "yyyy-MM-dd'T'HH:mm:ss.SSS'Z'",
                "yyyy-MM-dd'T'HH:mm:ss'Z'",
                "yyyy-MM-dd HH:mm:ss"
        );
        boolean isUtc = input != null && input.endsWith("Z");
        for (String inputFormat : possibleFormats) {
            try {
                SimpleDateFormat parser = new SimpleDateFormat(inputFormat, Locale.ENGLISH);
                parser.setLenient(false);
                // Only apply UTC when “Z” is present
                if (isUtc) {
                    parser.setTimeZone(TimeZone.getTimeZone("UTC"));
                }
                Date date = parser.parse(input);
                if (date != null) {
                    SimpleDateFormat formatter = new SimpleDateFormat(outputPattern, Locale.ENGLISH);
                    // Formatting should be in local timezone
                    formatter.setTimeZone(TimeZone.getDefault());
                    return formatter.format(date);
                }
            } catch (ParseException ignored) {
            }
        }
        return input; // fallback if no format matched
    }

    public static String timeAgo(Context context, String inputDate, boolean showDaysBeyondWeek) {
        if (StringUtil.isNullOrEmpty(inputDate)) {
            return "-";
        }

        List<String> possibleFormats = Arrays.asList(
                "yyyy-MM-dd'T'HH:mm:ss.SSSSSS'Z'",
                "yyyy-MM-dd'T'HH:mm:ss.SSS'Z'",
                "yyyy-MM-dd'T'HH:mm:ss'Z'",
                "yyyy-MM-dd HH:mm:ss"
        );
        Date date = null;
        boolean isUtc = inputDate != null && inputDate.endsWith("Z");
        for (String format : possibleFormats) {
            try {
                SimpleDateFormat parser = new SimpleDateFormat(format, Locale.ENGLISH);
                parser.setLenient(false);
                // Only apply UTC when “Z” is present
                if (isUtc) {
                    parser.setTimeZone(TimeZone.getTimeZone("UTC"));
                }
                date = parser.parse(inputDate);
                if (date != null) break;
            } catch (ParseException ignored) {
            }
        }

        if (date == null) return inputDate;

        long diffMillis = System.currentTimeMillis() - date.getTime();
        long seconds = diffMillis / 1000;
        long minutes = seconds / 60;
        long hours = minutes / 60;
        long days = hours / 24;

        if (seconds < 60) {
            return context.getString(R.string.time_ago_just_now);
        } else if (minutes < 60) {
            return String.format(context.getString(R.string.time_ago_mins_template), minutes);
        } else if (hours < 24) {
            return String.format(context.getString(R.string.time_ago_hours_template), hours);
        } else if (days < 7) {
            return String.format(context.getString(R.string.time_ago_days), days);
        } else {
            if (showDaysBeyondWeek) {
                return String.format(context.getString(R.string.time_ago_days), days);
            } else {
                SimpleDateFormat outputFormat = new SimpleDateFormat(FORMAT_YYYY_MM_DD_HH_MM_A, Locale.ENGLISH);
                // Formatting should be in local timezone
                outputFormat.setTimeZone(TimeZone.getDefault());
                return outputFormat.format(date);
            }
        }
    }
}
