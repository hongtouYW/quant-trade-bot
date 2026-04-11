package com.dj.user.model;

public class ItemSection {
    private int id;
    private int iconImgResId;
    private String title;
    private String info;

    public ItemSection(int id, int iconImgResId, String title, String info) {
        this.id = id;
        this.iconImgResId = iconImgResId;
        this.title = title;
        this.info = info;
    }

    public int getId() {
        return id;
    }

    public void setId(int id) {
        this.id = id;
    }

    public int getIconImgResId() {
        return iconImgResId;
    }

    public void setIconImgResId(int iconImgResId) {
        this.iconImgResId = iconImgResId;
    }

    public String getTitle() {
        return title;
    }

    public void setTitle(String title) {
        this.title = title;
    }

    public String getInfo() {
        return info;
    }

    public void setInfo(String info) {
        this.info = info;
    }
}
