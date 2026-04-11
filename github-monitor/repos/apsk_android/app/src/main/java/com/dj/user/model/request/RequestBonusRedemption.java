package com.dj.user.model.request;

public class RequestBonusRedemption {
    private String member_id;
    private int vip_id;

    public RequestBonusRedemption(String member_id) {
        this.member_id = member_id;
    }

    public RequestBonusRedemption(String member_id, int vip_id) {
        this.member_id = member_id;
        this.vip_id = vip_id;
    }
}
