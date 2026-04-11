package com.dj.user.enums;

public enum OTPVerificationChannel {
    PHONE("phone"),
    EMAIL("email"),
    GOOGLE("google");

    private final String value;

    OTPVerificationChannel(String value) {
        this.value = value;
    }

    public String getValue() {
        return value;
    }


    public static OTPVerificationChannel fromValue(String value) {
        for (OTPVerificationChannel type : OTPVerificationChannel.values()) {
            if (type.value.equalsIgnoreCase(value)) {
                return type;
            }
        }
        throw new IllegalArgumentException("Invalid OTPVerificationChannel value: " + value);
    }
}
