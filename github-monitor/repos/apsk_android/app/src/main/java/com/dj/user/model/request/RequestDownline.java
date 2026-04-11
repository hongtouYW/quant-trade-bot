package com.dj.user.model.request;

public class RequestDownline {
    private String user_id;
    private String member_id;

    public RequestDownline(String memberId) {
        this.user_id = memberId;
        this.member_id = memberId;
    }
}
