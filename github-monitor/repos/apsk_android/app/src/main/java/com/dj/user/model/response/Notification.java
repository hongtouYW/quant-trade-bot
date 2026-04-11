package com.dj.user.model.response;

public class Notification {
    private long notification_id;
    private long sender_id;
    private String sender_type;
    private long recipient_id;
    private String recipient_type;
    private String notification_type;
    private String title;
    private String notification_desc;
    private int is_read;
    private String messagetype;
    private int status;
    private int delete;
    private String created_on;
    private String updated_on;

    public String getNotification_id() {
        return String.valueOf(notification_id);
    }

    public void setNotification_id(long notification_id) {
        this.notification_id = notification_id;
    }

    public String getSender_id() {
        return String.valueOf(sender_id);
    }

    public void setSender_id(long sender_id) {
        this.sender_id = sender_id;
    }

    public String getSender_type() {
        return sender_type;
    }

    public void setSender_type(String sender_type) {
        this.sender_type = sender_type;
    }

    public String getRecipient_id() {
        return String.valueOf(recipient_id);
    }

    public void setRecipient_id(long recipient_id) {
        this.recipient_id = recipient_id;
    }

    public String getRecipient_type() {
        return recipient_type;
    }

    public void setRecipient_type(String recipient_type) {
        this.recipient_type = recipient_type;
    }

    public String getNotification_type() {
        return notification_type;
    }

    public void setNotification_type(String notification_type) {
        this.notification_type = notification_type;
    }

    public String getTitle() {
        return title;
    }

    public void setTitle(String title) {
        this.title = title;
    }

    public String getNotification_desc() {
        return notification_desc;
    }

    public void setNotification_desc(String notification_desc) {
        this.notification_desc = notification_desc;
    }

    public boolean getIs_read() {
        return is_read == 1;
    }

    public void setIs_read(int is_read) {
        this.is_read = is_read;
    }

    public String getMessagetype() {
        return messagetype;
    }

    public void setMessagetype(String messagetype) {
        this.messagetype = messagetype;
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
