package com.dj.manager.enums;

import com.dj.manager.R;

public enum TransactionType {
    TOP_UP("deposit", R.string.transaction_type_top_up, R.drawable.ic_in, "+", R.color.green_00FF26),
    YXI_TOP_UP("game_deposit", R.string.transaction_type_top_up_yxi, R.drawable.ic_in, "+", R.color.green_00FF26),
    WITHDRAWAL("withdraw", R.string.transaction_type_withdraw, R.drawable.ic_out, "-", R.color.white_FFFFFF),
    YXI_WITHDRAWAL("game_withdraw", R.string.transaction_type_withdraw_yxi, R.drawable.ic_out, "-", R.color.white_FFFFFF),
    MANAGER_SETTLEMENT("manager_settlement", R.string.transaction_type_manager_settlement, R.drawable.ic_manager_settlement, "-", R.color.white_FFFFFF),
    MANAGER_TOP_UP("manager_top_up", R.string.transaction_type_manager_top_up, R.drawable.ic_manager_top_up, "+", R.color.green_00FF26),
    CLOSE_SHOP("close_shop", R.string.transaction_type_close_shop, R.drawable.ic_shop, "-", R.color.white_FFFFFF),
    UNKNOWN("unknown", R.string.transaction_type_unknown , 0, "", R.color.white_FFFFFF);

    private final String value;
    private final int title;
    private final int iconResId;
    private final String symbol;
    private final int colorResId;

    TransactionType(String value, int title, int iconResId, String symbol, int colorResId) {
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


    public static TransactionType fromValue(String value) {
        for (TransactionType type : TransactionType.values()) {
            if (type.value.equalsIgnoreCase(value)) {
                return type;
            }
        }
        throw new IllegalArgumentException("Invalid TransactionType value: " + value);
    }
}
