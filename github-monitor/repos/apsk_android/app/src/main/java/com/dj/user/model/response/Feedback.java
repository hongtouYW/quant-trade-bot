package com.dj.user.model.response;

public class Feedback {
    private long feedbacktype_id;
    private long feedback_id;
    private long member_id;
    private long shop_id;
    private long manager_id;
    private String title;
    private String feedback_desc;
    private String photo;
    private int status;
    private int delete;
    private String created_on;
    private String updated_on;

    public String getFeedbacktype_id() {
        return String.valueOf(feedbacktype_id);
    }

    public void setFeedbacktype_id(long feedbacktype_id) {
        this.feedbacktype_id = feedbacktype_id;
    }

    public String getFeedback_id() {
        return String.valueOf(feedback_id);
    }

    public void setFeedback_id(long feedback_id) {
        this.feedback_id = feedback_id;
    }

    public String getMember_id() {
        return String.valueOf(member_id);
    }

    public void setMember_id(long member_id) {
        this.member_id = member_id;
    }

    public String getShop_id() {
        return String.valueOf(shop_id);
    }

    public void setShop_id(long shop_id) {
        this.shop_id = shop_id;
    }

    public String getManager_id() {
        return String.valueOf(manager_id);
    }

    public void setManager_id(long manager_id) {
        this.manager_id = manager_id;
    }

    public String getTitle() {
        return title;
    }

    public void setTitle(String title) {
        this.title = title;
    }

    public String getFeedback_desc() {
        return feedback_desc;
    }

    public void setFeedback_desc(String feedback_desc) {
        this.feedback_desc = feedback_desc;
    }

    public String getPhoto() {
        return photo;
    }

    public void setPhoto(String photo) {
        this.photo = photo;
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
