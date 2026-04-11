package com.dj.user.model.request;

import com.dj.user.util.NetworkUtils;

public class RequestPlayerTransferAll {
    private String member_id;
    private String ip;

    public RequestPlayerTransferAll(String memberId) {
        this.member_id = memberId;
    }

    public void fetchPublicIp(NetworkUtils.PublicIpCallback callback) {
        NetworkUtils.fetchPublicIPv4(ipAddress -> {
            this.ip = ipAddress;
            if (callback != null) callback.onResult(ipAddress);
        });
    }
}
