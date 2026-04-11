package com.dj.user.model.request;

public class RequestRandomBindVerifyOTP {
    private String member_id;
    private String phone;
    private String code;
    private String devicekey;

    public RequestRandomBindVerifyOTP(String memberId, String phone, String code, String deviceKey) {
        this.member_id = memberId;
        this.phone = phone;
        this.code = code;
        this.devicekey = deviceKey;
    }
}
