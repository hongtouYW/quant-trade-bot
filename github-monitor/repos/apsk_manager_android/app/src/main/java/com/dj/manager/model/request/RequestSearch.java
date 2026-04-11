package com.dj.manager.model.request;

public class RequestSearch {
    private String manager_id;
    private String type;
    private String search;

    public RequestSearch(String managerId, String type, String search) {
        this.manager_id = managerId;
        this.type = type;
        this.search = search;
    }
}
