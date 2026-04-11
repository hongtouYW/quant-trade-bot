package com.dj.user.enums;

import com.dj.user.R;

public enum TransactionStatus {
    SUCCESS(1, R.string.transaction_status_success, R.color.green_9EEE69),
    PENDING(0, R.string.transaction_status_processing, R.color.gray_E4E4E4),
    REJECTED(-1, R.string.transaction_status_failed, R.color.red_EE698C);

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

    public int getTitle() {
        return title;
    }

    public int getColorResId() {
        return colorResId;
    }

    public static TransactionStatus fromValue(int value) {
        for (TransactionStatus type : TransactionStatus.values()) {
            if (type.value == value) {
                return type;
            }
        }
        throw new IllegalArgumentException("Invalid TransactionStatus value: " + value);
    }
}
