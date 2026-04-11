package com.dj.user.model.request;

import com.google.gson.annotations.SerializedName;

public class RequestInvitationCodeNew {
    private String member_id;
    @SerializedName("default")
    private int isDefault;

    public RequestInvitationCodeNew(String member_id) {
        this.member_id = member_id;
        this.isDefault = 0;
    }
}
