package com.dj.user.model.request;

import com.dj.user.util.NetworkUtils;

public class RequestPlayerTopUpWithdraw {
    private String member_id;
    private String gamemember_id;
    private Double amount;
    private String ip;

    public RequestPlayerTopUpWithdraw(String memberId, String playerId) {
        this.member_id = memberId;
        this.gamemember_id = playerId;
    }

    public RequestPlayerTopUpWithdraw(String member_id, String gamemember_id, double amount) {
        this.member_id = member_id;
        this.gamemember_id = gamemember_id;
        this.amount = amount;
    }

    public void fetchPublicIp(NetworkUtils.PublicIpCallback callback) {
        NetworkUtils.fetchPublicIPv4(ipAddress -> {
            this.ip = ipAddress;
            if (callback != null) callback.onResult(ipAddress);
        });
    }
}
