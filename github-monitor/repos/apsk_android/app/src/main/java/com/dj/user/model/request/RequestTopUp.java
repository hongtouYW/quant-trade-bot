package com.dj.user.model.request;

public class RequestTopUp {
    private String member_id;
    private String payment_id;
    private double amount;
    private String device;

    public RequestTopUp(String memberId, String paymentId, double amount) {
        this.member_id = memberId;
        this.payment_id = paymentId;
        this.amount = amount;
        this.device = "android";
    }
}
