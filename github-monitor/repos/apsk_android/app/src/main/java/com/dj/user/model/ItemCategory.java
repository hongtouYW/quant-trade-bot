package com.dj.user.model;

public class ItemCategory {
    private int iconResourceId;
    private int selectedIconResourceId;
    private String title;
    private String type;

    public ItemCategory(int iconResourceId, int selectedIconResourceId, String title, String type) {
        this.iconResourceId = iconResourceId;
        this.selectedIconResourceId = selectedIconResourceId;
        this.title = title;
        this.type = type;
    }

    public ItemCategory(String title) {
        this.title = title;
    }

    public int getIconResourceId() {
        return iconResourceId;
    }

    public void setIconResourceId(int iconResourceId) {
        this.iconResourceId = iconResourceId;
    }

    public int getSelectedIconResourceId() {
        return selectedIconResourceId;
    }

    public void setSelectedIconResourceId(int selectedIconResourceId) {
        this.selectedIconResourceId = selectedIconResourceId;
    }

    public String getTitle() {
        return title;
    }

    public void setTitle(String title) {
        this.title = title;
    }

    public String getType() {
        return type;
    }

    public void setType(String type) {
        this.type = type;
    }
}
