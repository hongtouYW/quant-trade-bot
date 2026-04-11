package com.dj.manager.model.response;

public class ShopPin {
    private String shoppin_id;
    private long shop_id;
    private long manager_id;
    private String created_on;

    public String getShoppin_id() {
        return shoppin_id;
    }

    public void setShoppin_id(String shoppin_id) {
        this.shoppin_id = shoppin_id;
    }

    public String getShop_id() {
        return String.valueOf(shop_id);
    }

    public void setShop_id(long shop_id) {
        this.shop_id = shop_id;
    }

    public String getManager_id() {
        return String.valueOf(manager_id);
    }

    public void setManager_id(long manager_id) {
        this.manager_id = manager_id;
    }

    public String getCreated_on() {
        return created_on;
    }

    public void setCreated_on(String created_on) {
        this.created_on = created_on;
    }
}
