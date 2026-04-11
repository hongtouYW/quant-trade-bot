package com.dj.manager.model.request;

public class RequestDataState {
    private String user_id;
    private String country_code;

    public RequestDataState(String userId, String countryCode) {
        this.user_id = userId;
        this.country_code = countryCode;
    }
}
