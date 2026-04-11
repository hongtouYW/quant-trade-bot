package com.dj.user.model.request;

import com.dj.user.util.StringUtil;

public class RequestLoginRegister {
    private String phone;
    private String password;
    private String module;
    private String agent_code;
    private String invitecode;
    private String shop_code;
    private String devicekey;

    public RequestLoginRegister(String phone, String password, String module, String devicekey) {
        this.phone = phone;
        this.password = password;
        this.module = module;
        this.devicekey = devicekey;
    }

//    public RequestLoginRegister(String phone, String password, String module, String agentCode) {
//        this.phone = phone;
//        this.password = password;
//        this.module = module;
//        this.agent_code = agentCode;
//    }

    public RequestLoginRegister(String phone, String password, String module, String agentCode, String inviteCode, String shopCode) {
        this.phone = phone;
        this.password = password;
        this.module = module;
        this.agent_code = agentCode;
        this.invitecode = inviteCode;
        this.shop_code = shopCode;
    }

    public String getPhone() {
        return !StringUtil.isNullOrEmpty(phone) ? phone : "";
    }

    public String getPassword() {
        return !StringUtil.isNullOrEmpty(password) ? password : "";
    }

    public String getModule() {
        return !StringUtil.isNullOrEmpty(module) ? module : "";
    }

    public String getInvitationCode() {
        return invitecode;
    }
}
