package com.dj.manager.model.response;

import com.dj.manager.enums.ShopStatus;
import com.dj.manager.util.StringUtil;

import java.util.ArrayList;
import java.util.List;

public class Shop {
    private long shop_id;
    private String shop_login;
    private String shop_name;
    private String area_code;
    private String prefix;
    private String principal;
    private String balance;
    private String lowestbalance;
    private String lowestbalance_on;
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
    private String reason;
    private long manager_id;
    private int status;
    private int delete;
    private String created_on;
    private String updated_on;
    private int pinned;
    private int totalmember;
    private int totalplayer;
    private double totalincome;
    private Area areas;
    private boolean isSelected;

    public Shop(long shop_id) {
        this.shop_id = shop_id;
    }

    public Shop(long shop_id, String shop_name, boolean isSelected) {
        this.shop_id = shop_id;
        this.shop_name = shop_name;
        this.isSelected = isSelected;
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

    public double getPrincipal() {
        try {
            return Double.parseDouble(principal.replaceAll(",", ""));
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
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

    public double getLowestbalance() {
        try {
            return Double.parseDouble(lowestbalance.replaceAll(",", ""));
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setLowestbalance(String lowestbalance) {
        this.lowestbalance = lowestbalance;
    }

    public String getLowestbalance_on() {
        return !StringUtil.isNullOrEmpty(lowestbalance_on) ? lowestbalance_on : updated_on;
    }

    public void setLowestbalance_on(String lowestbalance_on) {
        this.lowestbalance_on = lowestbalance_on;
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

    public int getCan_deposit() {
        return can_deposit;
    }

    public void setCan_deposit(int can_deposit) {
        this.can_deposit = can_deposit;
    }

    public int getCan_withdraw() {
        return can_withdraw;
    }

    public void setCan_withdraw(int can_withdraw) {
        this.can_withdraw = can_withdraw;
    }

    public int getCan_create() {
        return can_create;
    }

    public void setCan_create(int can_create) {
        this.can_create = can_create;
    }

    public int getCan_block() {
        return can_block;
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

    public int getCan_income() {
        return can_income;
    }

    public void setCan_income(int can_income) {
        this.can_income = can_income;
    }

    public int getRead_clear() {
        return read_clear;
    }

    public void setRead_clear(int read_clear) {
        this.read_clear = read_clear;
    }

    public int getCan_view_credential() {
        return can_view_credential;
    }

    public void setCan_view_credential(int can_view_credential) {
        this.can_view_credential = can_view_credential;
    }

    public int getNo_withdrawal_fee() {
        return no_withdrawal_fee;
    }

    public void setNo_withdrawal_fee(int no_withdrawal_fee) {
        this.no_withdrawal_fee = no_withdrawal_fee;
    }

    public String getReason() {
        return reason;
    }

    public void setReason(String reason) {
        this.reason = reason;
    }

    public String getManager_id() {
        String managerId = String.valueOf(manager_id);
        return managerId.equalsIgnoreCase("0") ? "-" : managerId;
    }

    public void setManager_id(long manager_id) {
        this.manager_id = manager_id;
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

    public int getPinned() {
        return pinned;
    }

    public void setPinned(int pinned) {
        this.pinned = pinned;
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

    public boolean isSelected() {
        return isSelected;
    }

    public void setSelected(boolean selected) {
        isSelected = selected;
    }

    public boolean isPinned() {
        return pinned == 1;
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

    public String getShopLocation() {
        List<String> parts = new ArrayList<>();
        if (areas != null) {
            if (areas.getCountries() != null && !StringUtil.isNullOrEmpty(areas.getCountries().getCountry_name())) {
                parts.add(areas.getCountries().getCountry_name());
            }
            if (areas.getStates() != null && !StringUtil.isNullOrEmpty(areas.getStates().getState_name())) {
                parts.add(areas.getStates().getState_name());
            }
            if (!StringUtil.isNullOrEmpty(areas.getArea_name())) {
                parts.add(areas.getArea_name());
            }
        }
        if (!parts.isEmpty()) {
            return String.join(", ", parts);
        }
        return area_code != null ? area_code : "-";
    }

    public ShopStatus getShopStatus() {
        if (delete == 1) {
            return ShopStatus.DELETED;
        }
        if (status == 0) {
            return ShopStatus.CLOSED;
        }
        return ShopStatus.ACTIVE;
    }
}
