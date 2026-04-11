package com.dj.user.model.request;

import com.dj.user.util.NetworkUtils;

public class RequestPlayerDelete {
    private String member_id;
    private String gamemember_id;
    private String ip;

    public RequestPlayerDelete(String memberId, String gameMemberId) {
        this.member_id = memberId;
        this.gamemember_id = gameMemberId;
    }

    public void fetchPublicIp(NetworkUtils.PublicIpCallback callback) {
        NetworkUtils.fetchPublicIPv4(ipAddress -> {
            this.ip = ipAddress;
            if (callback != null) callback.onResult(ipAddress);
        });
    }
}
