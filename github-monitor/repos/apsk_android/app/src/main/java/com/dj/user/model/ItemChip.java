package com.dj.user.model;

public class ItemChip {
    private String label;
    private String value;
    private boolean showExtraView;
    private String startDate;
    private String endDate;

    public ItemChip(String label) {
        this.label = label;
    }

    public ItemChip(String label, String value) {
        this.label = label;
        this.value = value;
    }

    public ItemChip(String label, boolean showExtraView) {
        this.label = label;
        this.showExtraView = showExtraView;
    }

    public ItemChip(String label, boolean showExtraView, String startDate, String endDate) {
        this.label = label;
        this.showExtraView = showExtraView;
        this.startDate = startDate;
        this.endDate = endDate;
    }

    public String getLabel() {
        return label;
    }

    public void setLabel(String label) {
        this.label = label;
    }

    public String getValue() {
        return value;
    }

    public void setValue(String value) {
        this.value = value;
    }

    public boolean isShowExtraView() {
        return showExtraView;
    }

    public void setShowExtraView(boolean showExtraView) {
        this.showExtraView = showExtraView;
    }

    public String getStartDate() {
        return startDate;
    }

    public void setStartDate(String startDate) {
        this.startDate = startDate;
    }

    public String getEndDate() {
        return endDate;
    }

    public void setEndDate(String endDate) {
        this.endDate = endDate;
    }
}
