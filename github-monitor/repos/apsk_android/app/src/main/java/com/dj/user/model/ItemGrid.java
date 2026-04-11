package com.dj.user.model;

public class ItemGrid {
    private int id;
    private int imageResId;
    private String title;
    private String value;
    private String promoTag;
    private boolean isValueInOrange;

    public ItemGrid(String title, String value) {
        this.title = title;
        this.value = value;
    }

    public ItemGrid(String title, String value, boolean isValueInOrange) {
        this.title = title;
        this.value = value;
        this.isValueInOrange = isValueInOrange;
    }

    public ItemGrid(int id, int imageResId, String title, String promoTag) {
        this.id = id;
        this.imageResId = imageResId;
        this.title = title;
        this.promoTag = promoTag;
    }

    public int getId() {
        return id;
    }

    public void setId(int id) {
        this.id = id;
    }

    public int getImageResId() {
        return imageResId;
    }

    public void setImageResId(int imageResId) {
        this.imageResId = imageResId;
    }

    public String getTitle() {
        return title;
    }

    public void setTitle(String title) {
        this.title = title;
    }

    public String getValue() {
        return value;
    }

    public void setValue(String value) {
        this.value = value;
    }

    public String getPromoTag() {
        return promoTag;
    }

    public void setPromoTag(String promoTag) {
        this.promoTag = promoTag;
    }

    public boolean isValueInOrange() {
        return isValueInOrange;
    }

    public void setValueInOrange(boolean valueInOrange) {
        isValueInOrange = valueInOrange;
    }
}
