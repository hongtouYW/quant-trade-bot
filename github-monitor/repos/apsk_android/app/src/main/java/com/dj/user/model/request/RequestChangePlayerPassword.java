package com.dj.user.model.request;

public class RequestChangePlayerPassword {
    private String member_id;
    private String gamemember_id;
    private String password;

    public RequestChangePlayerPassword(String memberId, String playerId, String password) {
        this.member_id = memberId;
        this.gamemember_id = playerId;
        this.password = password;
    }
}
