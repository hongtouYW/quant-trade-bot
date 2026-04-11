package com.dj.shop.enums;

import androidx.annotation.NonNull;

import com.dj.shop.R;

public enum TransactionType {
    USER(R.string.transaction_type_user, R.drawable.ic_user_add, "", 0),
    RANDOM_USER(R.string.transaction_type_user_random, R.drawable.ic_user_add_random, "", 0),
    PLAYER_USER(R.string.transaction_type_user_player, R.drawable.ic_user_add_player, "", 0),
    TOP_UP(R.string.transaction_type_top_up, R.drawable.ic_in, "+", R.color.green_00FF26),
    YXI_TOP_UP(R.string.transaction_type_yxi_top_up, R.drawable.ic_in, "+", R.color.green_00FF26),
    WITHDRAWAL(R.string.transaction_type_withdraw, R.drawable.ic_out, "-", R.color.white_FFFFFF),
    YXI_WITHDRAWAL(R.string.transaction_type_yxi_withdraw, R.drawable.ic_out, "-", R.color.white_FFFFFF),
    MANAGER_SETTLEMENT(R.string.transaction_type_manager_settlement, R.drawable.ic_manager_settlement, "-", R.color.white_FFFFFF),
    MANAGER_TOP_UP(R.string.transaction_type_manager_top_up, R.drawable.ic_manager_top_up, "+", R.color.green_00FF26),
    CLOSE_SHOP(R.string.transaction_type_close_shop, R.drawable.ic_shop, "-", R.color.white_FFFFFF),
    UNKNOWN(R.string.transaction_type_unknown, 0, "", R.color.white_FFFFFF);

    private final int titleResId;
    private final int iconResId;
    private final String symbol;
    private final int colorResId;

    TransactionType(int titleResId, int iconResId, String symbol, int colorResId) {
        this.titleResId = titleResId;
        this.iconResId = iconResId;
        this.symbol = symbol;
        this.colorResId = colorResId;
    }

    public int getTitleResId() {
        return titleResId;
    }

    public int getIconResId() {
        return iconResId;
    }

    @NonNull
    public String getSymbol() {
        return symbol;
    }

    public int getColorResId() {
        return colorResId;
    }
}
