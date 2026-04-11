package com.dj.manager.model.request;

public class RequestShopReason {
    private String manager_id;
    private String shop_id;
    private String reason;

    public RequestShopReason(String managerId, String shopId, String reason) {
        this.manager_id = managerId;
        this.shop_id = shopId;
        this.reason = reason;
    }
}
