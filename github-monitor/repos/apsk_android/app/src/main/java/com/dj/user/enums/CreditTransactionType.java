package com.dj.user.enums;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.user.R;

public enum CreditTransactionType {
    NONE("", R.string.transaction_type_credit_unknown, R.string.transaction_type_credit_unknown_desc, "+", R.drawable.ic_add),
    COMMISSION("commission", R.string.transaction_type_credit_commission, R.string.transaction_type_credit_commission_desc, "+", R.drawable.ic_add),
    GENERAL_BONUS("firstbonus", R.string.transaction_type_credit_bonus_general, R.string.transaction_type_credit_bonus_general_desc, "+", R.drawable.ic_add),
    WEEKLY_BONUS("weeklybonus", R.string.transaction_type_credit_bonus_weekly, R.string.transaction_type_credit_bonus_weekly_desc, "+", R.drawable.ic_add),
    MONTHLY_BONUS("monthlybonus", R.string.transaction_type_credit_bonus_monthly, R.string.transaction_type_credit_bonus_monthly_desc, "+", R.drawable.ic_add),
    NEW_REGISTER("newmemberregister", R.string.transaction_type_credit_top_up_online, R.string.transaction_type_credit_top_up_online_desc, "+", R.drawable.ic_add),
    NEW_RELOAD("newmemberreload", R.string.transaction_type_credit_top_up_online, R.string.transaction_type_credit_top_up_online_desc, "+", R.drawable.ic_add),
    NEW_RECRUIT("newmemberrecruit", R.string.transaction_type_credit_top_up_online, R.string.transaction_type_credit_top_up_online_desc, "+", R.drawable.ic_add),
    NEW_YXI_RELOAD("newmembergamereload", R.string.transaction_type_credit_top_up_online, R.string.transaction_type_credit_top_up_online_desc, "+", R.drawable.ic_add),
    TOP_UP("deposit", R.string.transaction_type_credit_top_up_online, R.string.transaction_type_credit_top_up_online_desc, "+", R.drawable.ic_add),
    SHOP_TOP_UP("deposit", R.string.transaction_type_credit_top_up_shop, R.string.transaction_type_credit_top_up_shop_desc, "+", R.drawable.ic_add),
    YXI_TOP_UP("gamedeposit", R.string.transaction_type_credit_top_up_yxi, R.string.transaction_type_credit_top_up_yxi_desc, "+", R.drawable.ic_add),
    WITHDRAWAL("withdraw", R.string.transaction_type_credit_withdraw_online, R.string.transaction_type_credit_withdraw_online_desc, "-", R.drawable.ic_deduct),
    SHOP_WITHDRAWAL("withdraw", R.string.transaction_type_credit_withdraw_shop, R.string.transaction_type_credit_withdraw_shop_desc, "-", R.drawable.ic_deduct),
    SHOP_WITHDRAWAL_QR("withdraw", R.string.transaction_type_credit_withdraw_shop_qr, R.string.transaction_type_credit_withdraw_shop_qr_desc, "-", R.drawable.ic_deduct),
    YXI_WITHDRAWAL("gamewithdraw", R.string.transaction_type_credit_withdraw_yxi, R.string.transaction_type_credit_withdraw_yxi_desc, "-", R.drawable.ic_deduct);

    private final String value;
    private final int title;
    private final int label;
    private final String symbol;
    private final int iconResId;

    CreditTransactionType(String value, int label, int title, String symbol, int iconResId) {
        this.value = value;
        this.title = title;
        this.label = label;
        this.symbol = symbol;
        this.iconResId = iconResId;
    }

    @NonNull
    public String getValue() {
        return value;
    }

    public int getTitle() {
        return title;
    }

    public int getLabel() {
        return label;
    }

    @NonNull
    public String getSymbol() {
        return symbol;
    }

    public int getIconResId() {
        return iconResId;
    }

    @Nullable
    public static CreditTransactionType fromValue(@Nullable String value) {
        for (CreditTransactionType type : CreditTransactionType.values()) {
            if (type.value.equalsIgnoreCase(value)) {
                return type;
            }
        }
        throw new IllegalArgumentException("Invalid CreditTransactionType value: " + value);
    }
}
