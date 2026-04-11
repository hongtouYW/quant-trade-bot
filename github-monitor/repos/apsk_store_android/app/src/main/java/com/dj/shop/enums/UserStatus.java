package com.dj.shop.enums;

import androidx.annotation.Nullable;

import com.dj.shop.R;

public enum UserStatus {
    ACTIVE(1, R.string.user_status_active),
    BLOCKED(2, R.string.user_status_blocked),
    DELETED(3, R.string.user_status_deleted);

    private final int value;
    private final int title;

    UserStatus(int value, int title) {
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
    public static UserStatus fromValue(int value) {
        for (UserStatus type : UserStatus.values()) {
            if (type.value == value) {
                return type;
            }
        }
        throw new IllegalArgumentException("Invalid UserStatus value: " + value);
    }
}
