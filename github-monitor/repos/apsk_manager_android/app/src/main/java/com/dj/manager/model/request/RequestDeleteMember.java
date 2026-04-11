package com.dj.manager.model.request;

public class RequestDeleteMember {
    private String manager_id;
    private String member_id;
    private String reason;

    public RequestDeleteMember(String managerId, String memberId, String reason) {
        this.manager_id = managerId;
        this.member_id = memberId;
        this.reason = reason;
    }
}
