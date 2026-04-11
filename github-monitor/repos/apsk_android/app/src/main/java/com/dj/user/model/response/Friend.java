package com.dj.user.model.response;

public class Friend {
    private long member_id;
    private String referralCode;
    private String created_on;
    private int invitecount;
    private int creditcount;
    private String creditamount;

    public String getMember_id() {
        return String.valueOf(member_id);
    }

    public void setMember_id(long member_id) {
        this.member_id = member_id;
    }

    public String getReferralCode() {
        return referralCode;
    }

    public void setReferralCode(String referralCode) {
        this.referralCode = referralCode;
    }

    public String getCreated_on() {
        return created_on;
    }

    public void setCreated_on(String created_on) {
        this.created_on = created_on;
    }

    public int getInvitecount() {
        return invitecount;
    }

    public void setInvitecount(int invitecount) {
        this.invitecount = invitecount;
    }

    public int getCreditcount() {
        return creditcount;
    }

    public void setCreditcount(int creditcount) {
        this.creditcount = creditcount;
    }

    public double getCreditamount() {
        try {
            return Double.parseDouble(creditamount);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setCreditamount(String creditamount) {
        this.creditamount = creditamount;
    }
}
