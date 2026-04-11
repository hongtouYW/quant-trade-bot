package com.dj.manager.model.request;

public class RequestUpdateShopStatus {
    private String manager_id;
    private String shop_id;
    private int status;

    public RequestUpdateShopStatus(String managerId, String shopId, int status) {
        this.manager_id = managerId;
        this.shop_id = shopId;
        this.status = status;
    }
}
