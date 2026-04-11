package com.dj.user.model.request;

public class RequestResetPassword {
    private String member_id;
    private String newpassword;

    public RequestResetPassword(String memberId, String newPassword) {
        this.member_id = memberId;
        this.newpassword = newPassword;
    }
}
