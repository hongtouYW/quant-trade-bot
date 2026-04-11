package com.dj.user.model.request;

public class RequestPlayerPassword {
    private String user_id;
    private String gamemember_id;

    public RequestPlayerPassword(String memberId, String gameMemberId) {
        this.user_id = memberId;
        this.gamemember_id = gameMemberId;
    }
}
