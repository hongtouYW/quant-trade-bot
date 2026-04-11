package com.dj.user.model.response;

import com.dj.user.util.StringUtil;

public class Member {
    private long member_id;
    private String member_login;
    private String member_pass;
    private String member_name;
    private String full_name;
    private String uid;
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
    private String devicekey;
    private int vip;
    private long shop_id;
    private String downline;
    private String dob;
    private int alarm;
    private String reason;
    private String lastlogin_on;
    private int GAstatus;
    private String two_factor_secret;
    private String two_factor_recovery_codes;
    private String two_factor_confirmed_at;
    private int bindphone;
    private int bindemail;
    private int bindgoogle;
    private int status;
    private int delete;
    private String created_on;
    private String updated_on;
    private Area areas;
    private String avatar;

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

    public String getUid() {
        return uid;
    }

    public void setUid(String uid) {
        this.uid = uid;
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
        return !StringUtil.isNullOrEmpty(whatsapp) ? whatsapp : "-";
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
            return Double.parseDouble(balance);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setBalance(String balance) {
        this.balance = balance;
    }

    public String getDevicekey() {
        return devicekey;
    }

    public void setDevicekey(String devicekey) {
        this.devicekey = devicekey;
    }

    public int getVip() {
        return vip > 10 ? 0 : vip;
    }

    public void setVip(int vip) {
        this.vip = vip;
    }

    public String getShop_id() {
        return String.valueOf(shop_id);
    }

    public void setShop_id(long shop_id) {
        this.shop_id = shop_id;
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

    public int getBindphone() {
        return bindphone;
    }

    public void setBindphone(int bindphone) {
        this.bindphone = bindphone;
    }

    public int getBindemail() {
        return bindemail;
    }

    public void setBindemail(int bindemail) {
        this.bindemail = bindemail;
    }

    public int getBindgoogle() {
        return bindgoogle;
    }

    public void setBindgoogle(int bindgoogle) {
        this.bindgoogle = bindgoogle;
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

    public Area getAreas() {
        return areas;
    }

    public void setAreas(Area areas) {
        this.areas = areas;
    }

    public String getAvatar() {
        return avatar;
    }

    public void setAvatar(String avatar) {
        this.avatar = avatar;
    }

    public boolean isCreatedByShop() {
        return !StringUtil.isNullOrEmpty(String.valueOf(shop_id));
    }

    public String getMemberLocation() {
        if (areas == null ||
                areas.getCountries() == null ||
                areas.getCountries().getCountry_name() == null ||
                areas.getStates() == null ||
                areas.getStates().getState_name() == null) {
            return "-";
        }
        return String.format("%s, %s", areas.getCountries().getCountry_name(), areas.getStates().getState_name());
    }

    public boolean isPhoneBinded() {
        return bindphone == 1;
    }

    public boolean isEmailBinded() {
        return bindemail == 1;
    }

    public boolean isGoogleAuthenticatorBinded() {
        return bindgoogle == 1;
    }
}
