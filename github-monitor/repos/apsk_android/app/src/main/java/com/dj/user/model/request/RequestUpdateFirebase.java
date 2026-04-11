package com.dj.user.model.request;


public class RequestUpdateFirebase {
    private String member_id;
    private String devicekey;

    public RequestUpdateFirebase(String memberId, String deviceKey) {
        this.member_id = memberId;
        this.devicekey = deviceKey;
    }
}