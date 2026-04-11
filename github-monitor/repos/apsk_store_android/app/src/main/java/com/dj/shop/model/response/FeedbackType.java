package com.dj.shop.model.response;

public class FeedbackType {
    private long feedbacktype_id;
    private String title;
    private String feedback_type;
    private String feedback_desc;
    private String type;
    private int status;
    private int delete;
    private String created_on;
    private String updated_on;
    private boolean isSelected;

    public String getFeedbacktype_id() {
        return String.valueOf(feedbacktype_id);
    }

    public void setFeedbacktype_id(long feedbacktype_id) {
        this.feedbacktype_id = feedbacktype_id;
    }

    public String getTitle() {
        return title;
    }

    public void setTitle(String title) {
        this.title = title;
    }

    public String getFeedback_type() {
        return feedback_type;
    }

    public void setFeedback_type(String feedback_type) {
        this.feedback_type = feedback_type;
    }

    public String getFeedback_desc() {
        return feedback_desc;
    }

    public void setFeedback_desc(String feedback_desc) {
        this.feedback_desc = feedback_desc;
    }

    public String getType() {
        return type;
    }

    public void setType(String type) {
        this.type = type;
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

    public boolean isSelected() {
        return isSelected;
    }

    public void setSelected(boolean selected) {
        isSelected = selected;
    }
}
