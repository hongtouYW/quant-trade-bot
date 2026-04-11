package com.dj.manager.model.request;

import java.util.List;

public class RequestSystemLog {
    private String manager_id;
    private String type;
    private List<String> manager_ids;
    private List<String> shop_ids;

    public RequestSystemLog(String managerId, String type) {
        this.manager_id = managerId;
        this.type = type;
    }

    public RequestSystemLog(String manager_id, String type, List<String> manager_ids, List<String> shop_ids) {
        this.manager_id = manager_id;
        this.type = type;
        this.manager_ids = manager_ids;
        this.shop_ids = shop_ids;
    }
}
