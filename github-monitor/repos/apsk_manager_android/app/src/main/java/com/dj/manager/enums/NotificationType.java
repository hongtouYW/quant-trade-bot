package com.dj.manager.enums;

import com.dj.manager.R;

public enum NotificationType {
    ADMIN("admin", 0),
    MEMBER("member", 0),
    MANAGER("manager", 0),
    SHOP("shop", R.string.notification_action_shop_details),
    EVENT("event", 0),
    VERSION("version", R.string.notification_action_app_update),
    GAME("game", 0),
    ALERT("alert", R.string.notification_action_remove_alarm);

    private final String value;
    private final int title;

    NotificationType(String value, int title) {
        this.value = value;
        this.title = title;
    }

    public String getValue() {
        return value;
    }

    public int getTitle() {
        return title;
    }

    public static NotificationType fromValue(String value) {
        for (NotificationType type : NotificationType.values()) {
            if (type.value.equalsIgnoreCase(value)) {
                return type;
            }
        }
        throw new IllegalArgumentException("Invalid NotificationType value: " + value);
    }
}
