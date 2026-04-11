package com.dj.manager.model.response;

import java.util.List;

public class SystemLogFilter {
    private List<Shop> shop;
    private List<Manager> manager;

    public List<Shop> getShop() {
        return shop;
    }

    public void setShop(List<Shop> shop) {
        this.shop = shop;
    }

    public List<Manager> getManager() {
        return manager;
    }

    public void setManager(List<Manager> manager) {
        this.manager = manager;
    }
}
