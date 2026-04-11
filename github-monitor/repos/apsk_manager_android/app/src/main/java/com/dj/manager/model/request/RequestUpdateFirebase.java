package com.dj.manager.model.request;

public class RequestUpdateFirebase {
    private String manager_id;
    private String devicekey;

    public RequestUpdateFirebase(String managerId, String deviceKey) {
        this.manager_id = managerId;
        this.devicekey = deviceKey;
    }
}
