package com.dj.manager.model.request;

public class RequestStatusData {
    private String manager_id;
    private int status;

    public RequestStatusData(String managerId, int status) {
        this.manager_id = managerId;
        this.status = status;
    }
}
