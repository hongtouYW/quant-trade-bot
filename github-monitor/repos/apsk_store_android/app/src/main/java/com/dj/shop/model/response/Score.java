package com.dj.shop.model.response;

public class Score {
    private long member_id;
    private String type;
    private double amount;
    private String created_on;
    private String updated_on;
    private long score_id;

    public long getMember_id() {
        return member_id;
    }

    public void setMember_id(long member_id) {
        this.member_id = member_id;
    }

    public String getType() {
        return type;
    }

    public void setType(String type) {
        this.type = type;
    }

    public double getAmount() {
        return amount;
    }

    public void setAmount(double amount) {
        this.amount = amount;
    }

    public String getCreated_on() {
        return created_on;
    }

    public void setCreated_on(String created_on) {
        this.created_on = created_on;
    }

    public String getUpdated_on() {
        return updated_on;
    }

    public void setUpdated_on(String updated_on) {
        this.updated_on = updated_on;
    }

    public long getScore_id() {
        return score_id;
    }

    public void setScore_id(long score_id) {
        this.score_id = score_id;
    }
}
