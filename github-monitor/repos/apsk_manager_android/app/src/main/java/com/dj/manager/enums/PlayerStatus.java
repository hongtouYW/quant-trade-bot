package com.dj.manager.enums;

import com.dj.manager.R;

public enum PlayerStatus {
    ACTIVE(1, R.string.player_status_active, R.color.green_29B83C),
    BLOCKED(2, R.string.player_status_blocked, R.color.gray_7B7B7B),
    DELETED(3, R.string.player_status_deleted, R.color.red_D32424);

    private final int value;
    private final int title;
    private final int colorResId;

    PlayerStatus(int value, int title, int colorResId) {
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

    public static PlayerStatus fromValue(int value) {
        for (PlayerStatus type : PlayerStatus.values()) {
            if (type.value == value) {
                return type;
            }
        }
        throw new IllegalArgumentException("Invalid PlayerStatus value: " + value);
    }
}
