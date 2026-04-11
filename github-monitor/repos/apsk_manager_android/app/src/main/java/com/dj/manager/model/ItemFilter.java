package com.dj.manager.model;

import com.dj.manager.enums.LogFilterType;

import java.util.List;

public class ItemFilter {
    private LogFilterType logFilterType;
    private String desc;
    private List<String> selectedIds;
    private boolean isSelected;

    public ItemFilter(LogFilterType logFilterType, String desc, boolean isSelected) {
        this.logFilterType = logFilterType;
        this.desc = desc;
        this.isSelected = isSelected;
    }

    public LogFilterType getLogFilterType() {
        return logFilterType;
    }

    public void setLogFilterType(LogFilterType logFilterType) {
        this.logFilterType = logFilterType;
    }

    public String getDesc() {
        return desc;
    }

    public void setDesc(String desc) {
        this.desc = desc;
    }

    public List<String> getSelectedIds() {
        return selectedIds;
    }

    public void setSelectedIds(List<String> selectedIds) {
        this.selectedIds = selectedIds;
    }

    public boolean isSelected() {
        return isSelected;
    }

    public void setSelected(boolean selected) {
        isSelected = selected;
    }
}
