package com.dj.user.enums;

import com.dj.user.R;

public enum PromotionCategory {
    NEW_MEMBER_RELOAD("newmemberreload", "ic_promotion_new", R.string.promotion_category_new_reload),
    CRYPTO_RELOAD("cryptoreload", "ic_promotion_crypto", R.string.promotion_category_crypto_reload),
    AGENT("agent", "ic_promotion_agent", R.string.promotion_category_agent),
    POPULAR("hot", "ic_promotion_popular", R.string.promotion_category_popular),
    OTHERS("others", "ic_promotion_others", R.string.promotion_category_others);

    private final String value;
    private final int title;
    private final String iconResourceName;

    PromotionCategory(String value, String iconResourceName, int title) {
        this.value = value;
        this.iconResourceName = iconResourceName;
        this.title = title;
    }

    public String getValue() {
        return value;
    }

    public int getTitle() {
        return title;
    }

    public String getIconResourceName() {
        return iconResourceName;
    }

    public static PromotionCategory fromValue(String value) {
        for (PromotionCategory type : PromotionCategory.values()) {
            if (type.value.equalsIgnoreCase(value)) {
                return type;
            }
        }
        throw new IllegalArgumentException("Invalid PromotionCategory value: " + value);
    }
}
