package com.dj.user.enums;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.user.R;

public enum PointTransactionType {
    NONE("", R.string.transaction_type_credit_unknown, R.string.transaction_type_credit_unknown_desc, "+", R.drawable.ic_add),
    POINT_TOP_UP("reload", R.string.transaction_type_point_reload, R.string.transaction_type_point_reload_desc, "+", R.drawable.ic_add),
    POINT_WITHDRAW("withdraw", R.string.transaction_type_point_withdraw, R.string.transaction_type_point_withdraw_desc, "-", R.drawable.ic_deduct);

    private final String value;
    private final int label;
    private final int title;
    private final String symbol;
    private final int iconResId;

    PointTransactionType(String value, int label, int title, String symbol, int iconResId) {
        this.value = value;
        this.label = label;
        this.title = title;
        this.symbol = symbol;
        this.iconResId = iconResId;
    }

    @NonNull
    public String getValue() {
        return value;
    }

    public int getLabel() {
        return label;
    }

    public int getTitle() {
        return title;
    }

    @NonNull
    public String getSymbol() {
        return symbol;
    }

    public int getIconResId() {
        return iconResId;
    }

    @Nullable
    public static PointTransactionType fromValue(@Nullable String value) {
        for (PointTransactionType type : PointTransactionType.values()) {
            if (type.value.equalsIgnoreCase(value)) {
                return type;
            }
        }
        throw new IllegalArgumentException("Invalid CreditTransactionType value: " + value);
    }
}
