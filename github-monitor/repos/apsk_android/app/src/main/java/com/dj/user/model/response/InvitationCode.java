package com.dj.user.model.response;

import com.google.gson.annotations.SerializedName;

public class InvitationCode {
    private long invitation_id;
    private String referralCode;
    private String invitecode;
    private String invitecode_name;
    private String qr;
    @SerializedName("default")
    private int isDefault;
    private String created_on;

    private boolean editing;

    public String getInvitation_id() {
        return String.valueOf(invitation_id);
    }

    public void setInvitation_id(long invitation_id) {
        this.invitation_id = invitation_id;
    }

    public String getReferralCode() {
        return referralCode;
    }

    public void setReferralCode(String referralCode) {
        this.referralCode = referralCode;
    }

    public String getInvitecode() {
        return invitecode;
    }

    public void setInvitecode(String invitecode) {
        this.invitecode = invitecode;
    }

    public String getInvitecode_name() {
        return invitecode_name;
    }

    public void setInvitecode_name(String invitecode_name) {
        this.invitecode_name = invitecode_name;
    }

    public String getQr() {
        return qr;
    }

    public void setQr(String qr) {
        this.qr = qr;
    }

    public int getIsDefault() {
        return isDefault;
    }

    public void setIsDefault(int isDefault) {
        this.isDefault = isDefault;
    }

    public String getCreated_on() {
        return created_on;
    }

    public void setCreated_on(String created_on) {
        this.created_on = created_on;
    }

    public boolean isDefault() {
        return isDefault == 1;
    }

    public boolean isEditing() {
        return editing;
    }

    public void setEditing(boolean editing) {
        this.editing = editing;
    }
}
