package com.dj.user.enums;

import com.dj.user.R;

public enum VIPType {
    GENERAL("firstbonus", R.string.vip_tab_bonus),
    //    DAILY("dailybonus", "日俸禄"),
    WEEKLY("weeklybonus", R.string.vip_tab_weekly),
    MONTHLY("monthlybonus", R.string.vip_tab_monthly),
    VIP("vip", R.string.vip_tab_vip);

    private final String value;
    private final int title;

    VIPType(String value, int title) {
        this.value = value;
        this.title = title;
    }

    public String getValue() {
        return value;
    }

    public int getTitle() {
        return title;
    }

    public static VIPType fromValue(String value) {
        for (VIPType type : VIPType.values()) {
            if (type.value.equalsIgnoreCase(value)) {
                return type;
            }
        }
        throw new IllegalArgumentException("Invalid VIPType value: " + value);
    }
}
