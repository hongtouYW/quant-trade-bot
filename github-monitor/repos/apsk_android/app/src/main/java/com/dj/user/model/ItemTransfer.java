package com.dj.user.model;

public class ItemTransfer {
    private String yxi;
    private double points;
    private double transfer;
    private boolean isHeader;

    public ItemTransfer() {
    }

    public ItemTransfer(boolean isHeader) {
        this.isHeader = isHeader;
    }

    public ItemTransfer(String yxi, double points, double transfer) {
        this.yxi = yxi;
        this.points = points;
        this.transfer = transfer;
    }

    public String getYxi() {
        return yxi;
    }

    public void setYxi(String yxi) {
        this.yxi = yxi;
    }

    public double getPoints() {
        return points;
    }

    public void setPoints(double points) {
        this.points = points;
    }

    public double getTransfer() {
        return transfer;
    }

    public void setTransfer(double transfer) {
        this.transfer = transfer;
    }

    public boolean isHeader() {
        return isHeader;
    }

    public void setHeader(boolean header) {
        isHeader = header;
    }
}
