package com.dj.shop.model.request;

public class RequestPlayerNew {
    private String shop_id;
    private String provider_id;

    public RequestPlayerNew(String shopId, String providerId) {
        this.shop_id = shopId;
        this.provider_id = providerId;
    }
}
