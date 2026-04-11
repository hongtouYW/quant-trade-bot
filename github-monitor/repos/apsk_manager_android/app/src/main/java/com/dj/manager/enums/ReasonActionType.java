package com.dj.manager.enums;

import com.dj.manager.R;

public enum ReasonActionType {
    CLOSE_SHOP(R.string.reason_action_close_shop),
    BLOCK_USER(R.string.reason_action_block_user),
    DELETE_USER(R.string.reason_action_delete_user);

    private final int title;

    ReasonActionType(int title) {
        this.title = title;
    }

    public int getTitle() {
        return title;
    }

    public static ReasonActionType fromValue(int value) {
        for (ReasonActionType type : ReasonActionType.values()) {
            if (type.title == value) {
                return type;
            }
        }
        throw new IllegalArgumentException("Invalid ReasonActionType value: " + value);
    }
}
