package com.dj.manager.model;

public interface Selectable {
    boolean isSelected();

    void setSelected(boolean selected);

    String getSearchableText();
}