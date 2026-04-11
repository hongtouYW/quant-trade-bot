package com.dj.manager.model.request;

public class RequestUpdateShopPassword {
    private String manager_id;
    private String shop_id;
    private String newpassword;

    public RequestUpdateShopPassword(String managerId, String shopId, String newPassword) {
        this.manager_id = managerId;
        this.shop_id = shopId;
        this.newpassword = newPassword;
    }
}
