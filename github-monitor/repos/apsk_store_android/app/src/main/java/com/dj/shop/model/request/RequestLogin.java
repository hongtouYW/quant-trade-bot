package com.dj.shop.model.request;

public class RequestLogin {
    private String login;
    private String password;
    private String devicekey;

    public RequestLogin(String login, String password, String deviceID) {
        this.login = login;
        this.password = password;
        this.devicekey = deviceID;
    }
}
