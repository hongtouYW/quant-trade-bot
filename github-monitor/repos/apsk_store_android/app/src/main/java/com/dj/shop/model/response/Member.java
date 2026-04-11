package com.dj.shop.model.response;

import com.dj.shop.enums.UserStatus;

public class Member {
    private long member_id;
    private String member_login;
    private String member_pass;
    private String member_name;
    private String full_name;
    private String area_code;
    private String prefix;
    private String phone;
    private String email;
    private String wechat;
    private String whatsapp;
    private String facebook;
    private String telegram;
    private String bank_account;
    private String bank_id;
    private String bank_full_name;
    private String balance;
    private int vip;
    private String downline;
    private String dob;
    private int alarm;
    private String reason;
    private String lastlogin_on;
    private int GAstatus;
    private String two_factor_secret;
    private String two_factor_recovery_codes;
    private String two_factor_confirmed_at;
    private int status;
    private int delete;
    private String created_on;
    private String updated_on;

    public Member(long member_id, String phone) {
        this.member_id = member_id;
        this.phone = phone;
    }

    public String getMember_id() {
        return String.valueOf(member_id);
    }

    public void setMember_id(long member_id) {
        this.member_id = member_id;
    }

    public String getMember_login() {
        return member_login;
    }

    public void setMember_login(String member_login) {
        this.member_login = member_login;
    }

    public String getMember_pass() {
        return member_pass;
    }

    public void setMember_pass(String member_pass) {
        this.member_pass = member_pass;
    }

    public String getMember_name() {
        return member_name;
    }

    public void setMember_name(String member_name) {
        this.member_name = member_name;
    }

    public String getFull_name() {
        return full_name;
    }

    public void setFull_name(String full_name) {
        this.full_name = full_name;
    }

    public String getArea_code() {
        return area_code;
    }

    public void setArea_code(String area_code) {
        this.area_code = area_code;
    }

    public String getPrefix() {
        return prefix;
    }

    public void setPrefix(String prefix) {
        this.prefix = prefix;
    }

    public String getPhone() {
        return phone;
    }

    public void setPhone(String phone) {
        this.phone = phone;
    }

    public String getEmail() {
        return email;
    }

    public void setEmail(String email) {
        this.email = email;
    }

    public String getWechat() {
        return wechat;
    }

    public void setWechat(String wechat) {
        this.wechat = wechat;
    }

    public String getWhatsapp() {
        return whatsapp;
    }

    public void setWhatsapp(String whatsapp) {
        this.whatsapp = whatsapp;
    }

    public String getFacebook() {
        return facebook;
    }

    public void setFacebook(String facebook) {
        this.facebook = facebook;
    }

    public String getTelegram() {
        return telegram;
    }

    public void setTelegram(String telegram) {
        this.telegram = telegram;
    }

    public String getBank_account() {
        return bank_account;
    }

    public void setBank_account(String bank_account) {
        this.bank_account = bank_account;
    }

    public String getBank_id() {
        return bank_id;
    }

    public void setBank_id(String bank_id) {
        this.bank_id = bank_id;
    }

    public String getBank_full_name() {
        return bank_full_name;
    }

    public void setBank_full_name(String bank_full_name) {
        this.bank_full_name = bank_full_name;
    }

    public double getBalance() {
        try {
            return Double.parseDouble(balance.replaceAll(",", ""));
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setBalance(String balance) {
        this.balance = balance;
    }

    public int getVip() {
        return vip;
    }

    public void setVip(int vip) {
        this.vip = vip;
    }

    public String getDownline() {
        return downline;
    }

    public void setDownline(String downline) {
        this.downline = downline;
    }

    public String getDob() {
        return dob;
    }

    public void setDob(String dob) {
        this.dob = dob;
    }

    public int getAlarm() {
        return alarm;
    }

    public void setAlarm(int alarm) {
        this.alarm = alarm;
    }

    public String getReason() {
        return reason;
    }

    public void setReason(String reason) {
        this.reason = reason;
    }

    public String getLastlogin_on() {
        return lastlogin_on;
    }

    public void setLastlogin_on(String lastlogin_on) {
        this.lastlogin_on = lastlogin_on;
    }

    public int getGAstatus() {
        return GAstatus;
    }

    public void setGAstatus(int GAstatus) {
        this.GAstatus = GAstatus;
    }

    public String getTwo_factor_secret() {
        return two_factor_secret;
    }

    public void setTwo_factor_secret(String two_factor_secret) {
        this.two_factor_secret = two_factor_secret;
    }

    public String getTwo_factor_recovery_codes() {
        return two_factor_recovery_codes;
    }

    public void setTwo_factor_recovery_codes(String two_factor_recovery_codes) {
        this.two_factor_recovery_codes = two_factor_recovery_codes;
    }

    public String getTwo_factor_confirmed_at() {
        return two_factor_confirmed_at;
    }

    public void setTwo_factor_confirmed_at(String two_factor_confirmed_at) {
        this.two_factor_confirmed_at = two_factor_confirmed_at;
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

    public UserStatus getUserStatus() {
        if (delete == 1) {
            return UserStatus.DELETED;
        }
        if (status == 0) {
            return UserStatus.BLOCKED;
        }
        return UserStatus.ACTIVE;
    }
}
