package com.dj.user.enums;

import com.dj.user.R;

public enum HelpCategory {
    GENERAL("commonquestion", R.string.help_tab_common),
    TRANSACTION("transactioncontrol", R.string.help_tab_transaction),
    TRANSFER("transfer", R.string.help_tab_transfer),
    VIP("vip", R.string.help_tab_vip),
    AFFILIATE("affiliate", R.string.help_tab_affiliate),
    AGENT("agent", R.string.help_tab_agent);

    private final String value;
    private final int title;

    HelpCategory(String value, int title) {
        this.value = value;
        this.title = title;
    }

    public String getValue() {
        return value;
    }

    public int getTitle() {
        return title;
    }

    public static HelpCategory fromValue(String value) {
        for (HelpCategory type : HelpCategory.values()) {
            if (type.value.equalsIgnoreCase(value)) {
                return type;
            }
        }
        throw new IllegalArgumentException("Invalid HelpCategory value: " + value);
    }
}
