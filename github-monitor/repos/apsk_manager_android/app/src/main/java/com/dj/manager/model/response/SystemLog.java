package com.dj.manager.model.response;

public class SystemLog {
    private long log_id;
    private long user_id;
    private String area_code;
    private String log_type;
    private String log_text;
    private LogDesc log_desc;
    private String created_on;

    public String getLog_id() {
        return String.valueOf(log_id);
    }

    public void setLog_id(long log_id) {
        this.log_id = log_id;
    }

    public String getUser_id() {
        return String.valueOf(user_id);
    }

    public void setUser_id(long user_id) {
        this.user_id = user_id;
    }

    public String getArea_code() {
        return area_code;
    }

    public void setArea_code(String area_code) {
        this.area_code = area_code;
    }

    public String getLog_type() {
        return log_type;
    }

    public void setLog_type(String log_type) {
        this.log_type = log_type;
    }

    public String getLog_text() {
        return log_text;
    }

    public void setLog_text(String log_text) {
        this.log_text = log_text;
    }

    public LogDesc getLog_desc() {
        return log_desc;
    }

    public void setLog_desc(LogDesc log_desc) {
        this.log_desc = log_desc;
    }

    public String getCreated_on() {
        return created_on;
    }

    public void setCreated_on(String created_on) {
        this.created_on = created_on;
    }
}
