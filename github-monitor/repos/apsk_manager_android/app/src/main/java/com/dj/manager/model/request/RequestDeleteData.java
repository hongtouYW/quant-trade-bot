package com.dj.manager.model.request;

public class RequestDeleteData {
    private String manager_id;
    private int delete;

    public RequestDeleteData(String managerId, int delete) {
        this.manager_id = managerId;
        this.delete = delete;
    }
}
