package com.dj.user.model.request;

public class RequestBindEmail {
    private String member_id;
    private String email;

    public RequestBindEmail(String memberId, String email) {
        this.member_id = memberId;
        this.email = email;
    }

    public String getMember_id() {
        return member_id;
    }

    public String getEmail() {
        return email;
    }
}
