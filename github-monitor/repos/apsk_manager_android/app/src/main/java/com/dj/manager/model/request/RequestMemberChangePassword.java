package com.dj.manager.model.request;

public class RequestMemberChangePassword {
    private String manager_id;
    private String member_id;

    public RequestMemberChangePassword(String mangerId, String memberId) {
        this.manager_id = mangerId;
        this.member_id = memberId;
    }
}
