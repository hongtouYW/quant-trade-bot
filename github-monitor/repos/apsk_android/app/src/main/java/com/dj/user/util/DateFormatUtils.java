package com.dj.user.util;

import java.text.ParseException;
import java.text.SimpleDateFormat;
import java.util.Arrays;
import java.util.Date;
import java.util.List;
import java.util.Locale;
import java.util.TimeZone;

public class DateFormatUtils {
    public static final String FORMAT_DD_MM_YYYY = "dd/MM/yyyy";
    public static final String FORMAT_DD_MM_YYYY_HH_MM_A = "dd/MM/yyyy HH:mm a";
    public static final String FORMAT_DD_MMM_YYYY_HH_MM_A = "dd MMM yyyy, HH:mm a";
    public static final String FORMAT_YYYY_MM_DD = "yyyy MM.dd";
    public static final String FORMAT_YYYY_MM_DD_DASHED = "yyyy-MM-dd";
    public static final String FORMAT_YYYY_MM_DD_HH_MM_SS_DASHED = "yyyy-MM-dd HH:mm:ss";

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
        return input; // fallback
    }
}
