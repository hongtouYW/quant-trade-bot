package com.dj.shop.model.request;

public class RequestMemberBlockReason {
    private String shop_id;
    private String member_id;
    private String reason;

    public RequestMemberBlockReason(String shop_id, String member_id, String reason) {
        this.shop_id = shop_id;
        this.member_id = member_id;
        this.reason = reason;
    }
}
