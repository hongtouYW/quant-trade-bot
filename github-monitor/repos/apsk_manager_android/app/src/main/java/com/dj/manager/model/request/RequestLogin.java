package com.dj.manager.model.request;

public class RequestLogin {
    private String login;
    private String password;
    private String devicekey;

    public RequestLogin(String login, String password, String devicekey) {
        this.login = login;
        this.password = password;
        this.devicekey = devicekey;
    }
}
