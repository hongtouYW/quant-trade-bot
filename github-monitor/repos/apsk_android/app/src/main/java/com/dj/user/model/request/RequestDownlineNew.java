package com.dj.user.model.request;

public class RequestDownlineNew {
    private String member_id;
    private String phone;
    private String password;

    public RequestDownlineNew(String memberId, String phone, String password) {
        this.member_id = memberId;
        this.phone = phone;
        this.password = password;
    }
}
