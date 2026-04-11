package com.dj.manager.model.request;

public class RequestUpdateShopAmount {
    private String manager_id;
    private String shop_id;
    private double principal;
    private double lowestbalance;

    public RequestUpdateShopAmount(String managerId, String shopId, double amount, double minBalance) {
        this.manager_id = managerId;
        this.shop_id = shopId;
        this.principal = amount;
        this.lowestbalance = minBalance;
    }
}
