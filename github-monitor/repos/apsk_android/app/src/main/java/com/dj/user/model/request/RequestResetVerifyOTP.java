package com.dj.user.model.request;

public class RequestResetVerifyOTP {
    private String member_id;
    private String code;
    private String verifyby;

    public RequestResetVerifyOTP(String memberId, String code) {
        this.member_id = memberId;
        this.code = code;
        this.verifyby = "phone";
    }

    public String getMember_id() {
        return member_id;
    }

    public String getCode() {
        return code;
    }
}
