package com.dj.manager.enums;

import com.dj.manager.R;

public enum PointType {
    BONUS("bonus", R.string.point_type_bonus, R.drawable.ic_point_bonus, "+", R.color.green_00FF26),
    REWARD("reward", R.string.point_type_reward, R.drawable.ic_point_reward, "+", R.color.green_00FF26),
    RELOAD("reload", R.string.point_type_top_up, R.drawable.ic_point_in, "+", R.color.green_00FF26),
    WITHDRAWAL("withdraw", R.string.point_type_withdraw, R.drawable.ic_point_out, "-", R.color.white_FFFFFF);

    private final String value;
    private final int title;
    private final int iconResId;
    private final String symbol;
    private final int colorResId;

    PointType(String value, int title, int iconResId, String symbol, int colorResId) {
        this.value = value;
        this.title = title;
        this.iconResId = iconResId;
        this.symbol = symbol;
        this.colorResId = colorResId;
    }

    public String getValue() {
        return value;
    }

    public int getTitle() {
        return title;
    }

    public int getIconResId() {
        return iconResId;
    }

    public String getSymbol() {
        return symbol;
    }

    public int getColorResId() {
        return colorResId;
    }


    public static PointType fromValue(String value) {
        for (PointType type : PointType.values()) {
            if (type.value.equalsIgnoreCase(value)) {
                return type;
            }
        }
        throw new IllegalArgumentException("Invalid PointType value: " + value);
    }
}
