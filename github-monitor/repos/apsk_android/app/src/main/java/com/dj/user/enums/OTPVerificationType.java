package com.dj.user.enums;

public enum OTPVerificationType {
    LOGIN(0),
    REGISTER(1),
    RESET_PASSWORD(2);

    private final int value;

    OTPVerificationType(int value) {
        this.value = value;
    }

    public int getValue() {
        return value;
    }


    public static OTPVerificationType fromValue(int value) {
        for (OTPVerificationType type : OTPVerificationType.values()) {
            if (type.value == value) {
                return type;
            }
        }
        throw new IllegalArgumentException("Invalid OTPVerificationType value: " + value);
    }
}
