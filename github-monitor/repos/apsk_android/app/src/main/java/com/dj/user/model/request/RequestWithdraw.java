package com.dj.user.model.request;

public class RequestWithdraw {
    private String member_id;
    private String bankaccount_id;
    private double amount;

    private String bank_id;
    private String bank_account;
    private String bank_full_name;

    public RequestWithdraw(String member_id, double amount) {
        this.member_id = member_id;
        this.amount = amount;
    }

    public RequestWithdraw(String memberId, String bankAccountId, double amount) {
        this.member_id = memberId;
        this.bankaccount_id = bankAccountId;
        this.amount = amount;
    }

    public RequestWithdraw(String member_id, double amount, String bank_id, String bank_account, String bank_full_name) {
        this.member_id = member_id;
        this.amount = amount;
        this.bank_id = bank_id;
        this.bank_account = bank_account;
        this.bank_full_name = bank_full_name;
    }
}
