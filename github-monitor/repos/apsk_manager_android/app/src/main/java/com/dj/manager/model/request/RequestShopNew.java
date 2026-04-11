package com.dj.manager.model.request;

public class RequestShopNew {
    private String manager_id;
    private String shop_name;
    private String login;
    private String shop_pass;
    private double principal;
    private String area_code;
    private int can_deposit;
    private int can_withdraw;
    private int can_create;
    private int can_block;
    private int can_income;
    private int read_clear;
    private int can_view_credential;
    private int no_withdrawal_fee;

    public RequestShopNew(String manager_id, String shop_name, String login, String shop_pass, double principal, String area_code, int can_deposit, int can_withdraw, int can_create, int can_block, int showIncome, int showSettlement, int can_view_credential, int no_withdrawal_fee) {
        this.manager_id = manager_id;
        this.shop_name = shop_name;
        this.login = login;
        this.shop_pass = shop_pass;
        this.principal = principal;
        this.area_code = area_code;
        this.can_deposit = can_deposit;
        this.can_withdraw = can_withdraw;
        this.can_create = can_create;
        this.can_block = can_block;
        this.can_income = showIncome;
        this.read_clear = showSettlement;
        this.can_view_credential = can_view_credential;
        this.no_withdrawal_fee = no_withdrawal_fee;
    }
}
