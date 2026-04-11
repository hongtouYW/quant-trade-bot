package com.dj.manager.model.request;

import com.dj.manager.util.NetworkUtils;

public class RequestPlayerDelete {
    private String manager_id;
    private String gamemember_id;
    private String ip;

    public RequestPlayerDelete(String managerId, String gamememberId) {
        this.manager_id = managerId;
        this.gamemember_id = gamememberId;
    }

    public void fetchPublicIp(NetworkUtils.PublicIpCallback callback) {
        NetworkUtils.fetchPublicIPv4(ipAddress -> {
            this.ip = ipAddress;
            if (callback != null) callback.onResult(ipAddress);
        });
    }
}
