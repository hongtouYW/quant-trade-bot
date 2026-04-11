package com.dj.manager.model;

public interface ItemBinder<T> {
    String getId(T item);

    String getTitle(T item);

    boolean isSelected(T item);

    void setSelected(T item, boolean selected);
}
