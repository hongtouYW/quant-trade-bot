package com.dj.manager.model.request;

public class RequestMemberReason {
    private String manager_id;
    private String member_id;
    private String reason;

    public RequestMemberReason(String managerId, String memberId, String reason) {
        this.manager_id = managerId;
        this.member_id = memberId;
        this.reason = reason;
    }
}
