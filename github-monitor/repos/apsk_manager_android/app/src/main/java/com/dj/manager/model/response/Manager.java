package com.dj.manager.model.response;

public class Manager {
    private long manager_id;
    private String manager_login;
    private String manager_name;
    private String full_name;
    private String area_code;
    private String prefix;
    private String phone;
    private String devicekey;
    private String balance;
    private String principal;
    private String lastlogin_on;
    private int GAstatus;
    private String two_factor_secret;
    private String two_factor_recovery_codes;
    private String two_factor_confirmed_at;
    private int status;
    private int delete;
    private String created_on;
    private String updated_on;

    private Area areas;
    private boolean isSelected;

    public Manager(long manager_id, String manager_name, boolean isSelected) {
        this.manager_id = manager_id;
        this.manager_name = manager_name;
        this.isSelected = isSelected;
    }

    public String getManager_id() {
        return String.valueOf(manager_id);
    }

    public void setManager_id(long manager_id) {
        this.manager_id = manager_id;
    }

    public String getManager_login() {
        return manager_login;
    }

    public void setManager_login(String manager_login) {
        this.manager_login = manager_login;
    }

    public String getManager_name() {
        return manager_name;
    }

    public void setManager_name(String manager_name) {
        this.manager_name = manager_name;
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

    public String getDevicekey() {
        return devicekey;
    }

    public void setDevicekey(String devicekey) {
        this.devicekey = devicekey;
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
