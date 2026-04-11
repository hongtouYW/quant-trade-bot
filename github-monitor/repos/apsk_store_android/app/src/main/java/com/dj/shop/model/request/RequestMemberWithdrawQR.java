package com.dj.shop.model.request;

public class RequestMemberWithdrawQR {
    private String shop_id;
    private String member_id;
    private String credit_id;
    private String password;

    public RequestMemberWithdrawQR(String shopId, String memberId, String creditId, String password) {
        this.shop_id = shopId;
        this.member_id = memberId;
        this.credit_id = creditId;
        this.password = password;
    }
}
