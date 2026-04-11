package com.dj.user.model.request;

public class RequestVIP {
    private String member_id;
    private String type;

    public RequestVIP(String memberId, String type) {
        this.member_id = memberId;
        this.type = type;
    }
}
