package com.dj.user.model.request;

public class RequestRefresh {
    private String refresh_token;

    public RequestRefresh(String refreshToken) {
        this.refresh_token = refreshToken;
    }
}
