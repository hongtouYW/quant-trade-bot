package com.dj.user.util;

public class PasswordUtils {
    public static int getPasswordStrengthLevel(String password) {
        if (password == null || password.isEmpty()) return 0;
        int level = 0;
        if (password.length() >= 8) level++; // length
        if (password.matches(".*\\d.*")) level++; // has digit
        if (password.matches(".*[a-z].*") && password.matches(".*[A-Z].*"))
            level++; // has both upper and lower case
        if (password.matches(".*[@#$%^&+=!~*()_\\-].*")) level++; // has special character
        return level;
    }
}
