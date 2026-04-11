package com.dj.manager.model;

public class ItemLanguage implements Selectable {
    private int id;
    private String title;
    private String code;
    private boolean isSelected;

    public ItemLanguage(int id, String title, String code) {
        this.id = id;
        this.title = title;
        this.code = code;
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

    public String getCode() {
        return code;
    }

    public void setCode(String code) {
        this.code = code;
    }

    public boolean isSelected() {
        return isSelected;
    }

    public void setSelected(boolean selected) {
        isSelected = selected;
    }

    @Override
    public String getSearchableText() {
        return title;
    }
}
