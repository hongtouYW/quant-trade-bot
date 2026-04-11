package com.dj.shop.model.request;

public class RequestUpdateFirebase {
    private String shop_id;
    private String devicekey;

    public RequestUpdateFirebase(String shopId, String devicekey) {
        this.shop_id = shopId;
        this.devicekey = devicekey;
    }
}
