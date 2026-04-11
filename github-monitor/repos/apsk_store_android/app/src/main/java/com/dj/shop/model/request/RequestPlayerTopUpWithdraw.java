package com.dj.shop.model.request;

import com.dj.shop.util.NetworkUtils;

public class RequestPlayerTopUpWithdraw {
    private String shop_id;
    private String member_id;
    private String gamemember_id;
    private double amount;
    private String ip;

    public RequestPlayerTopUpWithdraw(String shop_id, String member_id, String gamemember_id, double amount) {
        this.shop_id = shop_id;
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
