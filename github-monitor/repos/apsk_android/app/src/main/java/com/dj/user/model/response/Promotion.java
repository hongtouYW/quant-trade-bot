package com.dj.user.model.response;

public class Promotion {
    private long promotion_id;
    private String promotion_type;
    private String title;
    private String promotion_desc;
    private String vip_id;
    private long agent_id;
    private String amount;
    private String percent;
    private String photo;
    private int newbie;
    private int status;
    private int delete;
    private String created_on;
    private String updated_on;

    public String getPromotion_id() {
        return String.valueOf(promotion_id);
    }

    public void setPromotion_id(long promotion_id) {
        this.promotion_id = promotion_id;
    }

    public String getPromotion_type() {
        return promotion_type;
    }

    public void setPromotion_type(String promotion_type) {
        this.promotion_type = promotion_type;
    }

    public String getTitle() {
        return title;
    }

    public void setTitle(String title) {
        this.title = title;
    }

    public String getPromotion_desc() {
        return promotion_desc;
    }

    public void setPromotion_desc(String promotion_desc) {
        this.promotion_desc = promotion_desc;
    }

    public String getVip_id() {
        return vip_id;
    }

    public void setVip_id(String vip_id) {
        this.vip_id = vip_id;
    }

    public String getAgent_id() {
        return String.valueOf(agent_id);
    }

    public void setAgent_id(long agent_id) {
        this.agent_id = agent_id;
    }

    public String getAmount() {
        return amount;
    }

    public void setAmount(String amount) {
        this.amount = amount;
    }

    public String getPercent() {
        return percent;
    }

    public void setPercent(String percent) {
        this.percent = percent;
    }

    public String getPhoto() {
        return photo;
    }

    public void setPhoto(String photo) {
        this.photo = photo;
    }

    public int getNewbie() {
        return newbie;
    }

    public void setNewbie(int newbie) {
        this.newbie = newbie;
    }

    public int getStatus() {
        return status;
    }

    public void setStatus(int status) {
        this.status = status;
    }

    public int getDelete() {
        return delete;
    }

    public void setDelete(int delete) {
        this.delete = delete;
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
}
