package com.dj.user.model.response;

import com.dj.user.util.StringUtil;

public class BankAccount {
    private long bankaccount_id;
    private long member_id;
    private long bank_id;
    private String bank_account;
    private String bank_full_name;
    private int fastpay;
    private int status;
    private int delete;
    private String created_on;
    private String updated_on;
    private Bank bank;
    private boolean isSelected;

    public String getBankaccount_id() {
        return String.valueOf(bankaccount_id);
    }

    public void setBankaccount_id(long bankaccount_id) {
        this.bankaccount_id = bankaccount_id;
    }

    public String getMember_id() {
        return String.valueOf(member_id);
    }

    public void setMember_id(long member_id) {
        this.member_id = member_id;
    }

    public String getBank_id() {
        return String.valueOf(bank_id);
    }

    public void setBank_id(long bank_id) {
        this.bank_id = bank_id;
    }

    public String getBank_account() {
        return bank_account;
    }

    public void setBank_account(String bank_account) {
        this.bank_account = bank_account;
    }

    public String getBank_full_name() {
        return bank_full_name;
    }

    public void setBank_full_name(String bank_full_name) {
        this.bank_full_name = bank_full_name;
    }

    public int getFastpay() {
        return fastpay;
    }

    public void setFastpay(int fastpay) {
        this.fastpay = fastpay;
    }

    public int getStatus() {
        return status;
    }

    public void setStatus(int status) {
        this.status = status;
    }

    public int getDelete() {
        return delete;
    }

    public void setDelete(int delete) {
        this.delete = delete;
    }

    public String getCreated_on() {
        return created_on;
    }

    public void setCreated_on(String created_on) {
        this.created_on = created_on;
    }

    public String getUpdated_on() {
        return updated_on;
    }

    public void setUpdated_on(String updated_on) {
        this.updated_on = updated_on;
    }

    public Bank getBank() {
        return bank;
    }

    public void setBank(Bank bank) {
        this.bank = bank;
    }

    public boolean isSelected() {
        return isSelected;
    }

    public void setSelected(boolean selected) {
        isSelected = selected;
    }

    public boolean isQuickPay() {
        return fastpay == 1;
    }

    public String getBankName() {
        if (bank == null) {
            return "-";
        }
        return !StringUtil.isNullOrEmpty(bank.getBank_name()) ? bank.getBank_name() : "-";
    }
}
