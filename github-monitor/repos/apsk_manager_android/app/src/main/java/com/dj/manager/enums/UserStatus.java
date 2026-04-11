package com.dj.manager.enums;

import com.dj.manager.R;

public enum UserStatus {
    ACTIVE(1, R.string.user_status_active, R.color.green_29B83C, R.string.user_status_action_block, R.color.gray_BCBCBC, R.drawable.bg_button_bordered),
    BLOCKED(2, R.string.user_status_blocked, R.color.gray_7B7B7B, R.string.user_status_action_unblock, R.color.green_29B83C, R.drawable.bg_button_bordered_green),
    DELETED(3, R.string.user_status_deleted, R.color.red_D32424, R.string.user_status_action_block, R.color.gray_BCBCBC, R.drawable.bg_button_bordered);

    private final int value;
    private final int title;
    private final int colorResId;
    private final int actionTitle;
    private final int actionColorResId;
    private final int actionBgResId;

    UserStatus(int value, int title, int colorResId, int actionTitle, int actionColorResId, int actionBgResId) {
        this.value = value;
        this.title = title;
        this.colorResId = colorResId;
        this.actionTitle = actionTitle;
        this.actionColorResId = actionColorResId;
        this.actionBgResId = actionBgResId;
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

    public int getActionTitle() {
        return actionTitle;
    }

    public int getActionColorResId() {
        return actionColorResId;
    }

    public int getActionBgResId() {
        return actionBgResId;
    }

    public static UserStatus fromValue(int value) {
        for (UserStatus type : UserStatus.values()) {
            if (type.value == value) {
                return type;
            }
        }
        throw new IllegalArgumentException("Invalid UserStatus value: " + value);
    }
}
