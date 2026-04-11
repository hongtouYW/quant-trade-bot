package com.dj.user.model.request;

public class RequestAddBank {
    private String member_id;
    private String bank_id;
    private String bank_account;
    private String bank_full_name;
    private int fastpay;

    public RequestAddBank(String memberId, String bankId, String bankAccount, String bankFullName, int quickPay) {
        this.member_id = memberId;
        this.bank_id = bankId;
        this.bank_account = bankAccount;
        this.bank_full_name = bankFullName;
        this.fastpay = quickPay;
    }
}
