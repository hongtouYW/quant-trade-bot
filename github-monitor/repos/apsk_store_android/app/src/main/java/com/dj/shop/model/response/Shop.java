package com.dj.shop.model.response;

public class Shop {
    private long shop_id;
    private String shop_login;
    private String shop_name;
    private String area_code;
    private String prefix;
    private String principal;
    private String balance;
    private String devicekey;
    private String lastlogin_on;
    private int GAstatus;
    private String two_factor_secret;
    private String two_factor_recovery_codes;
    private String two_factor_confirmed_at;
    private int can_deposit;
    private int can_withdraw;
    private int can_create;
    private int can_block;
    private int alarm;
    private int can_income;
    private int read_clear;
    private int can_view_credential;
    private int no_withdrawal_fee;
    private int status;
    private int delete;
    private String created_on;
    private String updated_on;
    private int totalmember;
    private int totalplayer;
    private double totalincome;
    private Area areas;

    private boolean isPinned;

    public Shop(int status, boolean isPinned) {
        this.status = status;
        this.isPinned = isPinned;
    }

    public String getShop_id() {
        return String.valueOf(shop_id);
    }

    public void setShop_id(long shop_id) {
        this.shop_id = shop_id;
    }

    public String getShop_login() {
        return shop_login;
    }

    public void setShop_login(String shop_login) {
        this.shop_login = shop_login;
    }

    public String getShop_name() {
        return shop_name;
    }

    public void setShop_name(String shop_name) {
        this.shop_name = shop_name;
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

    public String getPrincipal() {
        return principal;
    }

    public void setPrincipal(String principal) {
        this.principal = principal;
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

    public String getDevicekey() {
        return devicekey;
    }

    public void setDevicekey(String devicekey) {
        this.devicekey = devicekey;
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

    public boolean isCanDeposit() {
        return can_deposit == 1;
    }

    public void setCan_deposit(int can_deposit) {
        this.can_deposit = can_deposit;
    }

    public boolean isCanWithdraw() {
        return can_withdraw == 1;
    }

    public void setCan_withdraw(int can_withdraw) {
        this.can_withdraw = can_withdraw;
    }


    public boolean isCanCreate() {
        return can_create == 1;
    }

    public void setCan_create(int can_create) {
        this.can_create = can_create;
    }


    public boolean isCanBlock() {
        return can_block == 1;
    }

    public void setCan_block(int can_block) {
        this.can_block = can_block;
    }

    public int getAlarm() {
        return alarm;
    }

    public void setAlarm(int alarm) {
        this.alarm = alarm;
    }

    public boolean isCanIncome() {
        return can_income == 1;
    }

    public void setCan_income(int can_income) {
        this.can_income = can_income;
    }

    public boolean isReadClear() {
        return read_clear == 1;
    }

    public void setRead_clear(int read_clear) {
        this.read_clear = read_clear;
    }

    public boolean hasWithdrawalFee() {
        return no_withdrawal_fee == 1;
    }

    public void setNo_withdrawal_fee(int no_withdrawal_fee) {
        this.no_withdrawal_fee = no_withdrawal_fee;
    }

    public boolean canViewCredentials() {
        return can_view_credential == 1;
    }

    public void setCan_view_credential(int can_view_credential) {
        this.can_view_credential = can_view_credential;
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

    public int getTotalmember() {
        return totalmember;
    }

    public void setTotalmember(int totalmember) {
        this.totalmember = totalmember;
    }

    public int getTotalplayer() {
        return totalplayer;
    }

    public void setTotalplayer(int totalplayer) {
        this.totalplayer = totalplayer;
    }

    public double getTotalincome() {
        return totalincome;
    }

    public void setTotalincome(double totalincome) {
        this.totalincome = totalincome;
    }

    public Area getAreas() {
        return areas;
    }

    public void setAreas(Area areas) {
        this.areas = areas;
    }

    public boolean isPinned() {
        return isPinned;
    }

    public void setPinned(boolean pinned) {
        isPinned = pinned;
    }

    public String getCountryName() {
        if (areas != null && areas.getCountries() != null) {
            return areas.getCountries().getCountry_name();
        }
        return area_code;
    }

    public String getStateName() {
        if (areas != null && areas.getStates() != null) {
            return areas.getStates().getState_name();
        }
        return area_code;
    }

    public String getAreaName() {
        if (areas != null) {
            return areas.getArea_name();
        }
        return area_code;
    }

}
