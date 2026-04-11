package com.dj.manager.enums;

import com.dj.manager.R;

public enum ShopStatus {
    ACTIVE(1, R.string.shop_status_active, R.color.green_29B83C, R.string.shop_status_action_close, R.color.gray_BCBCBC, R.drawable.bg_button_bordered),
    CLOSED(2, R.string.shop_status_closed, R.color.gray_7B7B7B, R.string.shop_status_action_open, R.color.green_29B83C, R.drawable.bg_button_bordered_green),
    DELETED(3, R.string.shop_status_deleted, R.color.red_D32424, 0, 0, 0);

    private final int value;
    private final int title;
    private final int colorResId;
    private final int action;
    private final int actionColorResId;
    private final int bgResId;

    ShopStatus(int value, int title, int colorResId, int action, int actionColorResId, int bgResId) {
        this.value = value;
        this.title = title;
        this.colorResId = colorResId;
        this.action = action;
        this.actionColorResId = actionColorResId;
        this.bgResId = bgResId;
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

    public int getAction() {
        return action;
    }

    public int getActionColorResId() {
        return actionColorResId;
    }

    public int getBgResId() {
        return bgResId;
    }

    public static ShopStatus fromValue(int value) {
        for (ShopStatus type : ShopStatus.values()) {
            if (type.value == value) {
                return type;
            }
        }
        throw new IllegalArgumentException("Invalid ShopStatus value: " + value);
    }
}
