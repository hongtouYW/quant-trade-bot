package com.dj.user.model.request;

public class RequestPaymentType {
    private String user_id;
    private String type;

    public RequestPaymentType(String memberId) {
        this.user_id = memberId;
    }

    public RequestPaymentType(String memberId, String type) {
        this.user_id = memberId;
        this.type = type;
    }
}
