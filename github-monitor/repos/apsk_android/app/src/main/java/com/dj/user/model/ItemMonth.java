package com.dj.user.model;

public class ItemMonth {
    private int id;
    private String title;
    private boolean isSelected;
    private boolean isEnabled;

    public ItemMonth(int id, String title, boolean isSelected, boolean isEnabled) {
        this.id = id;
        this.title = title;
        this.isSelected = isSelected;
        this.isEnabled = isEnabled;
    }

    public int getId() {
        return id;
    }

    public void setId(int id) {
        this.id = id;
    }

    public String getTitle() {
        return title;
    }

    public void setTitle(String title) {
        this.title = title;
    }

    public boolean isSelected() {
        return isSelected;
    }

    public void setSelected(boolean selected) {
        isSelected = selected;
    }

    public boolean isEnabled() {
        return isEnabled;
    }

    public void setEnabled(boolean enabled) {
        isEnabled = enabled;
    }
}
