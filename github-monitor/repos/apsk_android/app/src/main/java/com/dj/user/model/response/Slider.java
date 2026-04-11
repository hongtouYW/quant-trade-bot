package com.dj.user.model.response;

public class Slider {
    private long slider_id;
    private String title;
    private String slider_desc;
    private int is_read;
    private String lang;
    private int status;
    private int delete;
    private String created_on;
    private String updated_on;

    public String getSlider_id() {
        return String.valueOf(slider_id);
    }

    public void setSlider_id(long slider_id) {
        this.slider_id = slider_id;
    }

    public String getTitle() {
        return title;
    }

    public void setTitle(String title) {
        this.title = title;
    }

    public String getSlider_desc() {
        return slider_desc;
    }

    public void setSlider_desc(String slider_desc) {
        this.slider_desc = slider_desc;
    }

    public int getIs_read() {
        return is_read;
    }

    public void setIs_read(int is_read) {
        this.is_read = is_read;
    }

    public String getLang() {
        return lang;
    }

    public void setLang(String lang) {
        this.lang = lang;
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

    public boolean isRead() {
        return is_read == 1;
    }
}
