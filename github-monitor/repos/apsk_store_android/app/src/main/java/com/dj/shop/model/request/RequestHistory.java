package com.dj.shop.model.request;

public class RequestHistory {
    private String shop_id;
    private String[] types;
    private int page;
    private int limit;

    public RequestHistory(String shop_id, String[] types, int page) {
        this.shop_id = shop_id;
        this.types = types;
        this.page = page;
        this.limit = 20;
    }
}
