package com.dj.user.model.request;

public class RequestYxi {
    private String member_id;
    private String gameplatform_id;
    private String provider_id;

    public RequestYxi(String memberId, String gamePlatformId, String providerId) {
        this.member_id = memberId;
        this.gameplatform_id = gamePlatformId;
        this.provider_id = providerId;
    }
}
