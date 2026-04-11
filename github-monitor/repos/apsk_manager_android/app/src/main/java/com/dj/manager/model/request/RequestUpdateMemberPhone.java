package com.dj.manager.model.request;

public class RequestUpdateMemberPhone {
    private String manager_id;
    private String member_id;
    private String phone;

    public RequestUpdateMemberPhone(String managerId, String memberId, String phone) {
        this.manager_id = managerId;
        this.member_id = memberId;
        this.phone = phone;
    }
}