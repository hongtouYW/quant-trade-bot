package com.dj.manager.model.request;

public class RequestMemberProfile {
    private String manager_id;
    private String member_id;

    public RequestMemberProfile(String managerId, String memberId) {
        this.manager_id = managerId;
        this.member_id = memberId;
    }
}
