package com.dj.shop.model;

public class ItemGrid {
    public int id;
    public int bgResId;
    public int imageResId;
    public String label;

    public ItemGrid(int id, int imageResId, String label) {
        this.id = id;
        this.imageResId = imageResId;
        this.label = label;
    }

    public ItemGrid(int id, int bgResId, int imageResId, String label) {
        this.id = id;
        this.bgResId = bgResId;
        this.imageResId = imageResId;
        this.label = label;
    }
}
