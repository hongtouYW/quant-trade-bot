package com.dj.manager.model.request;

public class RequestChangePassword {
    private String manager_id;
    private String oldpassword;
    private String newpassword;

    public RequestChangePassword(String mangerId, String oldPassword, String newPassword) {
        this.manager_id = mangerId;
        this.oldpassword = oldPassword;
        this.newpassword = newPassword;
    }
}
