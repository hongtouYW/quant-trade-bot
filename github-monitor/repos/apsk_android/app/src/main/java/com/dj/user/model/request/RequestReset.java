package com.dj.user.model.request;

import com.dj.user.util.StringUtil;

public class RequestReset {
    private String phone;

    public RequestReset(String phone) {
        this.phone = phone;
    }

    public String getPhone() {
        return !StringUtil.isNullOrEmpty(phone) ? phone : "";
    }
}
