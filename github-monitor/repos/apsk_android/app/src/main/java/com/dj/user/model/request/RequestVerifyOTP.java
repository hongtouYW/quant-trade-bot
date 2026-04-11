package com.dj.user.model.request;

public class RequestVerifyOTP {
    private String login;
    private String password;
    private String code;
    private String verifyby;
    private String module;
    private String agent_code;
    private String invitecode;
    private String shop_code;
    private String devicekey;

    public RequestVerifyOTP(String login, String password, String code, String verifyBy, String module, String agentCode, String inviteCode, String shopCode, String devicekey) {
        this.login = login;
        this.password = password;
        this.code = code;
        this.verifyby = verifyBy;
        this.module = module;
        this.agent_code = agentCode;
        this.invitecode = inviteCode;
        this.shop_code = shopCode;
        this.devicekey = devicekey;
    }
}
