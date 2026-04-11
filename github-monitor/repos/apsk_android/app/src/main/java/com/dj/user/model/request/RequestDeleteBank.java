package com.dj.user.model.request;

public class RequestDeleteBank {
    private String member_id;
    private String bankaccount_id;

    public RequestDeleteBank(String memberId, String bankAccountId) {
        this.member_id = memberId;
        this.bankaccount_id = bankAccountId;
    }
}
