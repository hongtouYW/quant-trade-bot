package com.dj.user.model.response;

public class FriendCommission {
    private String created_on;
    private long member_id;
    private String amount;

    public String getCreated_on() {
        return created_on;
    }

    public void setCreated_on(String created_on) {
        this.created_on = created_on;
    }

    public String getMember_id() {
        return String.valueOf(member_id);
    }

    public void setMember_id(long member_id) {
        this.member_id = member_id;
    }

    public double getAmount() {
        try {
            return Double.parseDouble(amount);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setAmount(String amount) {
        this.amount = amount;
    }
}
