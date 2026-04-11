package com.dj.user.model.request;

public class RequestYxiDetails {
    private String user_id;
    private String game_id;

    public RequestYxiDetails(String memberId, String gameId) {
        this.user_id = memberId;
        this.game_id = gameId;
    }
}
