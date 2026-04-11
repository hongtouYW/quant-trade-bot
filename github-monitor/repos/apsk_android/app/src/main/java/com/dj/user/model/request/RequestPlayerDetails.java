package com.dj.user.model.request;

public class RequestPlayerDetails {
    private String member_id;
    private String gamemember_id;

    public RequestPlayerDetails(String memberId, String gameMemberId) {
        this.member_id = memberId;
        this.gamemember_id = gameMemberId;
    }
}
