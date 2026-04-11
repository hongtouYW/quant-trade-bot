package com.dj.user.model.response;

public class YxiBookmark {
    private long providerbookmark_id;
    private String providerbookmark_name;
    private long provider_id;
    private long member_id;
    private int status;
    private int delete;
    private String created_on;
    private String updated_on;

    public String getProviderbookmark_id() {
        return String.valueOf(providerbookmark_id);
    }

    public void setProviderbookmark_id(long providerbookmark_id) {
        this.providerbookmark_id = providerbookmark_id;
    }

    public String getProviderbookmark_name() {
        return providerbookmark_name;
    }

    public void setProviderbookmark_name(String providerbookmark_name) {
        this.providerbookmark_name = providerbookmark_name;
    }

    public String getProvider_id() {
        return String.valueOf(provider_id);
    }

    public void setProvider_id(long provider_id) {
        this.provider_id = provider_id;
    }

    public String getMember_id() {
        return String.valueOf(member_id);
    }

    public void setMember_id(long member_id) {
        this.member_id = member_id;
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
