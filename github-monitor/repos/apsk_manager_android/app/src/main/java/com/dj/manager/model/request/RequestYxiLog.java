package com.dj.manager.model.request;

public class RequestYxiLog {
    private String manager_id;
    private String shop_id;
    private String provider_id;
    private String startdate;
    private String enddate;
    private int page;
    private int limit;

    public RequestYxiLog(String managerId, String shopId, String providerId, int page) {
        this.manager_id = managerId;
        this.shop_id = shopId;
        this.provider_id = providerId;
        this.page = page;
        this.limit = 20;
    }
}
