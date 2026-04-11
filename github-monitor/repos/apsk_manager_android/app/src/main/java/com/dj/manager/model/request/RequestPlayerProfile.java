package com.dj.manager.model.request;

public class RequestPlayerProfile {
    private String manager_id;
    private String gamemember_id;

    public RequestPlayerProfile(String managerId, String gamememberId) {
        this.manager_id = managerId;
        this.gamemember_id = gamememberId;
    }
}
