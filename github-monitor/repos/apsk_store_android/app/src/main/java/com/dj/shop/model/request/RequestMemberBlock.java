package com.dj.shop.model.request;

public class RequestMemberBlock {
    private String shop_id;
    private String member_id;

    public RequestMemberBlock(String shop_id, String member_id) {
        this.shop_id = shop_id;
        this.member_id = member_id;
    }
}
