package com.dj.user.model.request;

public class RequestPaymentStatus {
    private String user_id;
    private String credit_id;

    public RequestPaymentStatus(String memberId, String creditId) {
        this.user_id = memberId;
        this.credit_id = creditId;
    }
}
