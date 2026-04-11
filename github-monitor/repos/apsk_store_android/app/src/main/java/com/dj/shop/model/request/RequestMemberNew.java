package com.dj.shop.model.request;

public class RequestMemberNew {
    private String shop_id;
    private String phone;

    public RequestMemberNew(String shop_id, String phone) {
        this.shop_id = shop_id;
        this.phone = phone;
    }
}
