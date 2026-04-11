package com.dj.manager.model.request;

public class RequestBlockedData {
    private String manager_id;
    private int status;
    private int delete;

    public RequestBlockedData(String managerId, int status, int delete) {
        this.manager_id = managerId;
        this.status = status;
        this.delete = delete;
    }
}
