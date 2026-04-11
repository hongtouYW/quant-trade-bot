package com.dj.user.model.request;

public class RequestPlayer {
    private String member_id;
    private String provider_id;

    public RequestPlayer(String memberId, String providerId) {
        this.member_id = memberId;
        this.provider_id = providerId;
    }
}
