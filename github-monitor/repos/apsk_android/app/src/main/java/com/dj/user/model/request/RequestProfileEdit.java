package com.dj.user.model.request;

public class RequestProfileEdit {
    private String member_id;
    private String member_name;
    private String uid;
    private String dob;
    private String avatar;

    public RequestProfileEdit(String member_id, String member_name, String uid, String dob, String avatar) {
        this.member_id = member_id;
        this.member_name = member_name;
        this.uid = uid;
        this.dob = dob;
        this.avatar = avatar;
    }
}
