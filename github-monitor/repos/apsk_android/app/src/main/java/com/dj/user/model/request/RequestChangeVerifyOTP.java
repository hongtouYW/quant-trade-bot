package com.dj.user.model.request;

public class RequestChangeVerifyOTP {
    private String member_id;
    private String newpassword;
    private String code;
    private String verifyby;

    public RequestChangeVerifyOTP(String memberId, String newPassword, String code, String verifyBy) {
        this.member_id = memberId;
        this.newpassword = newPassword;
        this.code = code;
        this.verifyby = verifyBy;
    }
}
