package com.dj.shop.model.request;

public class RequestMemberWithdraw {
    private String shop_id;
    private String member_id;
    private double amount;
    private String password;

    public RequestMemberWithdraw(String shop_id, String member_id, double amount, String password) {
        this.shop_id = shop_id;
        this.member_id = member_id;
        this.amount = amount;
        this.password = password;
    }
}
