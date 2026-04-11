package com.dj.user.model.request;

public class RequestChangePassword {
    private String member_id;
    private String oldpassword;
    private String newpassword;

    public RequestChangePassword(String memberId, String oldPassword, String newPassword) {
        this.member_id = memberId;
        this.oldpassword = oldPassword;
        this.newpassword = newPassword;
    }

    public String getMember_id() {
        return member_id;
    }

    public String getNewpassword() {
        return newpassword;
    }
}
