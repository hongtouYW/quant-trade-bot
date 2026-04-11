package com.dj.manager.model.request;

public class RequestTransaction {
    private String manager_id;
    private String shop_id;
    private String type;
    private int page;
    private int limit;

    public RequestTransaction(String managerId, String shopId, String type, int page) {
        this.manager_id = managerId;
        this.shop_id = shopId;
        this.type = type;
        this.page = page;
        this.limit = 20;
    }
}
