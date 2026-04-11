package com.dj.manager.enums;

import com.dj.manager.R;

public enum LogFilterType {
    SYSTEM_ALL(1, R.string.log_filter_shop_manager, 0, "all"),
    YXI_ALL(1, R.string.yxi_log_filter_shop_yxi, 0, "all"),
    SHOP(2, R.string.log_filter_shop, R.string.log_filter_shop_unit, "shop"),
    MANAGER(3, R.string.log_filter_manager, R.string.log_filter_manager_unit, "manager"),
    YXI(3, R.string.yxi_log_filter_yxi, R.string.yxi_log_filter_yxi_unit, "yxi");

    private final int id;
    private final int title;
    private final int desc;
    private final String type;

    LogFilterType(int id, int title, int desc, String type) {
        this.id = id;
        this.title = title;
        this.desc = desc;
        this.type = type;
    }

    public int getId() {
        return id;
    }

    public int getTitle() {
        return title;
    }

    public int getDesc() {
        return desc;
    }

    public String getType() {
        return type;
    }

    public static LogFilterType fromValue(int id) {
        for (LogFilterType type : LogFilterType.values()) {
            if (type.id == id) {
                return type;
            }
        }
        throw new IllegalArgumentException("Invalid LogFilterType value: " + id);
    }
}
