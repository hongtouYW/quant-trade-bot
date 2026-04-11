package com.dj.manager.model.request;

public class RequestShopProfile {
    private String manager_id;
    private String shop_id;

    public RequestShopProfile(String managerId, String shopId) {
        this.manager_id = managerId;
        this.shop_id = shopId;
    }
}
