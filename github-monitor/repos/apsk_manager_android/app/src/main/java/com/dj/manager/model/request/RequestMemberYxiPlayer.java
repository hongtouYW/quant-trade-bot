package com.dj.manager.model.request;

public class RequestMemberYxiPlayer {
    private String manager_id;
    private String member_id;
    private String provider_id;

    public RequestMemberYxiPlayer(String managerId, String memberId, String providerId) {
        this.manager_id = managerId;
        this.member_id = memberId;
        this.provider_id = providerId;
    }
}
