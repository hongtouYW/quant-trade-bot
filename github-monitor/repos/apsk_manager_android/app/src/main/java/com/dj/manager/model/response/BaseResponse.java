package com.dj.manager.model.response;

import java.util.List;

public class BaseResponse<T> {
    private T data; // Only present when status == true
    private String error;
    private String message;
    private boolean status;
    private Token token; // Optional, may be null
    private String encode_sign;

    // For refresh token response (directly in root)
    private String access_token;
    private String refresh_token;
    private String token_type;
    private String expires_in;
    private String refresh_expires_in;
    private Pagination pagination;

    // Member change password
    private String password;

    // Dashboard
    private List<Notification> tbl_notification;
    private int totalshop;
    private String totalshopbalance;

    // New player
//    private Member member; // member[0]
    private String member_login;
    private String member_password;
    private Player player;
    private String player_login;
    private String player_name;
    private String player_password;

    // State/Area list
    private List<State> tbl_states;
    private List<Area> tbl_areas;

    // Search
    private List<Manager> manager;
    private List<Member> member;
    private List<Shop> shop;

    // Yxi log list
    private List<Transaction> history;
    private Pagination historypagination;

    public T getData() {
        return data;
    }

    public void setData(T data) {
        this.data = data;
    }

    public String getError() {
        return error;
    }

    public void setError(String error) {
        this.error = error;
    }

    public String getMessage() {
        return message;
    }

    public void setMessage(String message) {
        this.message = message;
    }

    public boolean isStatus() {
        return status;
    }

    public void setStatus(boolean status) {
        this.status = status;
    }

    public Token getToken() {
        return token;
    }

    public void setToken(Token token) {
        this.token = token;
    }

    public String getEncode_sign() {
        return encode_sign;
    }

    public void setEncode_sign(String encode_sign) {
        this.encode_sign = encode_sign;
    }

    public String getAccess_token() {
        return access_token;
    }

    public void setAccess_token(String access_token) {
        this.access_token = access_token;
    }

    public String getRefresh_token() {
        return refresh_token;
    }

    public void setRefresh_token(String refresh_token) {
        this.refresh_token = refresh_token;
    }

    public String getToken_type() {
        return token_type;
    }

    public void setToken_type(String token_type) {
        this.token_type = token_type;
    }

    public String getExpires_in() {
        return expires_in;
    }

    public void setExpires_in(String expires_in) {
        this.expires_in = expires_in;
    }

    public String getRefresh_expires_in() {
        return refresh_expires_in;
    }

    public void setRefresh_expires_in(String refresh_expires_in) {
        this.refresh_expires_in = refresh_expires_in;
    }

    public Pagination getPagination() {
        return pagination;
    }

    public void setPagination(Pagination pagination) {
        this.pagination = pagination;
    }

    public String getPassword() {
        return password;
    }

    public void setPassword(String password) {
        this.password = password;
    }

    public List<Notification> getTbl_notification() {
        return tbl_notification;
    }

    public void setTbl_notification(List<Notification> tbl_notification) {
        this.tbl_notification = tbl_notification;
    }

    public int getTotalshop() {
        return totalshop;
    }

    public void setTotalshop(int totalshop) {
        this.totalshop = totalshop;
    }

    public double getTotalshopbalance() {
        try {
            return Double.parseDouble(totalshopbalance.replaceAll(",", ""));
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setTotalshopbalance(String totalshopbalance) {
        this.totalshopbalance = totalshopbalance;
    }

    public String getMember_login() {
        return member_login;
    }

    public void setMember_login(String member_login) {
        this.member_login = member_login;
    }

    public String getMember_password() {
        return member_password;
    }

    public void setMember_password(String member_password) {
        this.member_password = member_password;
    }

    public Player getPlayer() {
        return player;
    }

    public void setPlayer(Player player) {
        this.player = player;
    }

    public String getPlayer_login() {
        return player_login;
    }

    public void setPlayer_login(String player_login) {
        this.player_login = player_login;
    }

    public String getPlayer_name() {
        return player_name;
    }

    public void setPlayer_name(String player_name) {
        this.player_name = player_name;
    }

    public String getPlayer_password() {
        return player_password;
    }

    public void setPlayer_password(String player_password) {
        this.player_password = player_password;
    }

    public List<State> getTbl_states() {
        return tbl_states;
    }

    public void setTbl_states(List<State> tbl_states) {
        this.tbl_states = tbl_states;
    }

    public List<Area> getTbl_areas() {
        return tbl_areas;
    }

    public void setTbl_areas(List<Area> tbl_areas) {
        this.tbl_areas = tbl_areas;
    }

    public List<Manager> getManager() {
        return manager;
    }

    public void setManager(List<Manager> manager) {
        this.manager = manager;
    }

    public List<Member> getMember() {
        return member;
    }

    public void setMember(List<Member> member) {
        this.member = member;
    }

    public List<Shop> getShop() {
        return shop;
    }

    public void setShop(List<Shop> shop) {
        this.shop = shop;
    }

    public List<Transaction> getHistory() {
        return history;
    }

    public void setHistory(List<Transaction> history) {
        this.history = history;
    }

    public Pagination getHistorypagination() {
        return historypagination;
    }

    public void setHistorypagination(Pagination historypagination) {
        this.historypagination = historypagination;
    }
}
