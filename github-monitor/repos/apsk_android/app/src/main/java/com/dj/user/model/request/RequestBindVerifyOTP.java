package com.dj.user.model.request;

public class RequestBindVerifyOTP {
    private String member_id;
    private String code;

    public RequestBindVerifyOTP(String memberId, String code) {
        this.member_id = memberId;
        this.code = code;
    }
}
