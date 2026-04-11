package com.dj.user.model.request;

public class RequestPlayerLogin {
    private String member_id;
    private String gamemember_id;
    private String game_id;

    public RequestPlayerLogin(String memberId, String playerId) {
        this.member_id = memberId;
        this.gamemember_id = playerId;
    }

    public RequestPlayerLogin(String memberId, String playerId, String gameId) {
        this.member_id = memberId;
        this.gamemember_id = playerId;
        this.game_id = gameId;
    }
}
