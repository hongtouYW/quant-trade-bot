package com.dj.shop.model.request;

public class RequestPlayerSearch {
    private String shop_id;
    private String provider_id;
    private String search;

    public RequestPlayerSearch(String shopId, String providerId, String playerId) {
        this.shop_id = shopId;
        this.provider_id = providerId;
        this.search = playerId;
    }
}
