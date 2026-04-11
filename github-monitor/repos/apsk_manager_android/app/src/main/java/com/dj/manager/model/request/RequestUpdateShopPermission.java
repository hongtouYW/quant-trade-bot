package com.dj.manager.model.request;

public class RequestUpdateShopPermission {
    private String manager_id;
    private String shop_id;
    private int can_deposit;
    private int can_withdraw;
    private int can_create;
    private int can_block;
    private int can_income;
    private int read_clear;
    private int can_view_credential;
    private int no_withdrawal_fee;
    private int alarm;

    public RequestUpdateShopPermission(String manager_id, String shop_id, int can_deposit, int can_withdraw, int can_create, int can_block, int can_income, int read_clear, int can_view_credential, int no_withdrawal_fee) {
        this.manager_id = manager_id;
        this.shop_id = shop_id;
        this.can_deposit = can_deposit;
        this.can_withdraw = can_withdraw;
        this.can_create = can_create;
        this.can_block = can_block;
        this.can_income = can_income;
        this.read_clear = read_clear;
        this.can_view_credential = can_view_credential;
        this.no_withdrawal_fee = no_withdrawal_fee;
        this.alarm = 0;
    }

    public void setManager_id(String manager_id) {
        this.manager_id = manager_id;
    }

    public void setShop_id(String shop_id) {
        this.shop_id = shop_id;
    }

    public void setCan_deposit(int can_deposit) {
        this.can_deposit = can_deposit;
    }

    public void setCan_withdraw(int can_withdraw) {
        this.can_withdraw = can_withdraw;
    }

    public void setCan_create(int can_create) {
        this.can_create = can_create;
    }

    public void setCan_block(int can_block) {
        this.can_block = can_block;
    }

    public void setCan_income(int can_income) {
        this.can_income = can_income;
    }

    public void setRead_clear(int read_clear) {
        this.read_clear = read_clear;
    }

    public void setCan_view_credential(int can_view_credential) {
        this.can_view_credential = can_view_credential;
    }

    public void setNo_withdrawal_fee(int no_withdrawal_fee) {
        this.no_withdrawal_fee = no_withdrawal_fee;
    }
}
