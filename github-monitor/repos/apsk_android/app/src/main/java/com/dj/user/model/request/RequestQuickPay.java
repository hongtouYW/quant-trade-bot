package com.dj.user.model.request;

public class RequestQuickPay {
    private String member_id;
    private String bankaccount_id;
    private int status;

    public RequestQuickPay(String memberId, String bankAccountId, int status) {
        this.member_id = memberId;
        this.bankaccount_id = bankAccountId;
        this.status = status;
    }
}
