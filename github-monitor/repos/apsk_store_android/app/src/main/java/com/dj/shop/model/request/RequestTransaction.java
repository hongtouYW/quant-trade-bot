package com.dj.shop.model.request;

public class RequestTransaction {
    private String shop_id;
    private String type;
    private int page;
    private int limit;

    public RequestTransaction(String shopId, String type, int page) {
        this.shop_id = shopId;
        this.type = type;
        this.page = page;
        this.limit = 20;
    }
}
