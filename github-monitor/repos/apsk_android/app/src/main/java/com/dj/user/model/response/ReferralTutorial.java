package com.dj.user.model.response;

public class ReferralTutorial {
    private String recruittutorial_id;
    private String title;
    private String picture;
    private String slogan;
    private String desc;
    private String lang;
    private long agent_id;
    private int status;
    private int delete;
    private String created_on;
    private String updated_on;

    public String getRecruittutorial_id() {
        return recruittutorial_id;
    }

    public void setRecruittutorial_id(String recruittutorial_id) {
        this.recruittutorial_id = recruittutorial_id;
    }

    public String getTitle() {
        return title;
    }

    public void setTitle(String title) {
        this.title = title;
    }

    public String getPicture() {
        return picture;
    }

    public void setPicture(String picture) {
        this.picture = picture;
    }

    public String getSlogan() {
        return slogan;
    }

    public void setSlogan(String slogan) {
        this.slogan = slogan;
    }

    public String getDesc() {
        return desc;
    }

    public void setDesc(String desc) {
        this.desc = desc;
    }

    public String getLang() {
        return lang;
    }

    public void setLang(String lang) {
        this.lang = lang;
    }

    public String getAgent_id() {
        return String.valueOf(agent_id);
    }

    public void setAgent_id(long agent_id) {
        this.agent_id = agent_id;
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
