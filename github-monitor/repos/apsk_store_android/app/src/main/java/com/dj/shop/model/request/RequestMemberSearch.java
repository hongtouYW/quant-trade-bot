package com.dj.shop.model.request;

public class RequestMemberSearch {
    private String shop_id;
    private String search;

    public RequestMemberSearch(String shop_id, String search) {
        this.shop_id = shop_id;
        this.search = search;
    }
}
