package com.dj.manager.model.request;

public class RequestPlayerPassword {
    private String user_id;
    private String gamemember_id;

    public RequestPlayerPassword(String managerId, String gamememberId) {
        this.user_id = managerId;
        this.gamemember_id = gamememberId;
    }
}
