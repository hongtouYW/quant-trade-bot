package com.dj.manager.enums;

import androidx.annotation.Nullable;

import com.dj.manager.R;

public enum TransactionStatus {
    SUCCESS(1, R.string.transaction_status_success),
    FAILED(0, R.string.transaction_status_failed);

    private final int value;
    private final int title;

    TransactionStatus(int value, int title) {
        this.value = value;
        this.title = title;
    }

    public int getValue() {
        return value;
    }

    @Nullable
    public int getTitle() {
        return title;
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
