package com.dj.user.model.request;

public class RequestChangeOTP {
    private String member_id;
    private String verifyby;

    public RequestChangeOTP(String memberId, String verifyBy) {
        this.member_id = memberId;
        this.verifyby = verifyBy;
    }
}
