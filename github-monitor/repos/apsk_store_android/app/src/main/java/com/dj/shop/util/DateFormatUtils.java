package com.dj.shop.util;

import java.text.ParseException;
import java.text.SimpleDateFormat;
import java.util.Arrays;
import java.util.Date;
import java.util.List;
import java.util.Locale;

public class DateFormatUtils {
    public static final String FORMAT_DD_MM_YYYY = "dd/MM/yyyy";
    public static final String FORMAT_YYYY_MM_DD = "yyyy MM.dd";

    public static String getCurrentDateTime() {
        SimpleDateFormat sdf = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.ENGLISH);
        return sdf.format(new Date());
    }

    public static String formatIsoDate(String input, String outputPattern) {
        List<String> possibleFormats = Arrays.asList(
                "yyyy-MM-dd'T'HH:mm:ss.SSSSSS'Z'",
                "yyyy-MM-dd HH:mm:ss"
        );
        for (String inputFormat : possibleFormats) {
            try {
                SimpleDateFormat parser = new SimpleDateFormat(inputFormat, Locale.ENGLISH);
                parser.setLenient(false);
                Date date = parser.parse(input);

                if (date != null) {
                    SimpleDateFormat formatter = new SimpleDateFormat(outputPattern, Locale.ENGLISH);
                    return formatter.format(date);
                }
            } catch (ParseException ignored) {
                // try next format
            }
        }
        return input; // fallback if no format matched
    }
}
