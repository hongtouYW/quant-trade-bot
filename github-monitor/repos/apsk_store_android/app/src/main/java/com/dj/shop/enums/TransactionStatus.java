package com.dj.shop.enums;

import androidx.annotation.Nullable;

import com.dj.shop.R;

public enum TransactionStatus {
    SUCCESS(1, R.string.transaction_status_success, R.color.gray_C2C3CB),
    FAILED(0, R.string.transaction_status_failed, R.color.gray_C2C3CB);

    private final int value;
    private final int title;
    private final int colorResId;

    TransactionStatus(int value, int title, int colorResId) {
        this.value = value;
        this.title = title;
        this.colorResId = colorResId;
    }

    public int getValue() {
        return value;
    }

    @Nullable
    public int getTitle() {
        return title;
    }

    public int getColorResId() {
        return colorResId;
    }

    @Nullable
    public static TransactionStatus fromValue(int value) {
        for (TransactionStatus type : TransactionStatus.values()) {
            if (type.value == value) {
                return type;
            }
        }
        throw new IllegalArgumentException("Invalid TransactionStatus value: " + value);
    }
}
