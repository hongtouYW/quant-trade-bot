package com.dj.user.model.request;

public class RequestBindPhone {
    private String member_id;
    private String phone;

    public RequestBindPhone(String memberId, String phone) {
        this.member_id = memberId;
        this.phone = phone;
    }

    public String getMember_id() {
        return member_id;
    }

    public String getPhone() {
        return phone;
    }
}
