package com.dj.user.model.response;

public class YxiPlatform {
    private long gameplatform_id;
    private String gameplatform_name;
    private String icon;
    private String api;
    private int status;
    private int delete;
    private String created_on;
    private String updated_on;
    private int isBookmark;
    private long gamebookmark_id;

    public String getGameplatform_id() {
        return String.valueOf(gameplatform_id);
    }

    public void setGameplatform_id(int gameplatform_id) {
        this.gameplatform_id = gameplatform_id;
    }

    public String getGameplatform_name() {
        return gameplatform_name;
    }

    public void setGameplatform_name(String gameplatform_name) {
        this.gameplatform_name = gameplatform_name;
    }

    public String getIcon() {
        return icon;
    }

    public void setIcon(String icon) {
        this.icon = icon;
    }

    public String getApi() {
        return api;
    }

    public void setApi(String api) {
        this.api = api;
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

    public boolean isBookmark() {
        return isBookmark == 1;
    }

    public void setIsBookmark(int isBookmark) {
        this.isBookmark = isBookmark;
    }

    public String getGamebookmark_id() {
        return String.valueOf(gamebookmark_id);
    }

    public void setGamebookmark_id(Long gamebookmark_id) {
        this.gamebookmark_id = gamebookmark_id;
    }
}
