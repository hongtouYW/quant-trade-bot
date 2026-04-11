package com.dj.shop.model.request;

public class RequestMemberTopUp {
    private String shop_id;
    private String search;
    private double amount;

    public RequestMemberTopUp(String shop_id, String search, double amount) {
        this.shop_id = shop_id;
        this.search = search;
        this.amount = amount;
    }
}
