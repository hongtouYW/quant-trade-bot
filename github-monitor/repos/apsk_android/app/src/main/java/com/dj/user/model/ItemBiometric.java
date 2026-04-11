package com.dj.user.model;

public class ItemBiometric {
    private int id;
    private int iconResId;
    private String title;
    private boolean isAvailable;
    private boolean isEnabled;

    public ItemBiometric(int id, int iconResId, String title, boolean isAvailable, boolean isEnabled) {
        this.id = id;
        this.iconResId = iconResId;
        this.title = title;
        this.isAvailable = isAvailable;
        this.isEnabled = isEnabled;
    }

    public int getId() {
        return id;
    }

    public void setId(int id) {
        this.id = id;
    }

    public int getIconResId() {
        return iconResId;
    }

    public void setIconResId(int iconResId) {
        this.iconResId = iconResId;
    }

    public String getTitle() {
        return title;
    }

    public void setTitle(String title) {
        this.title = title;
    }

    public boolean isAvailable() {
        return isAvailable;
    }

    public void setAvailable(boolean available) {
        isAvailable = available;
    }

    public boolean isEnabled() {
        return isEnabled;
    }

    public void setEnabled(boolean enabled) {
        isEnabled = enabled;
    }
}
