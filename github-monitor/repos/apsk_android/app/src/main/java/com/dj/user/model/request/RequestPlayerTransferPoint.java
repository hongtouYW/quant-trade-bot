package com.dj.user.model.request;

import com.dj.user.util.NetworkUtils;

public class RequestPlayerTransferPoint {
    private String member_id;
    private String gamemember_id_from;
    private String gamemember_id_to;
    private double amount;
    private String ip;

    public RequestPlayerTransferPoint(String memberId, String from, String to, double amount) {
        this.member_id = memberId;
        this.gamemember_id_from = from;
        this.gamemember_id_to = to;
        this.amount = amount;
    }

    public void fetchPublicIp(NetworkUtils.PublicIpCallback callback) {
        NetworkUtils.fetchPublicIPv4(ipAddress -> {
            this.ip = ipAddress;
            if (callback != null) callback.onResult(ipAddress);
        });
    }
}
