package com.dj.user.model.request;

public class RequestBindGoogleOTP {
    private String member_id;
    private String code;
    private int bind;

    public RequestBindGoogleOTP(String memberId, String code, int bind) {
        this.member_id = memberId;
        this.code = code;
        this.bind = bind;
    }
}
