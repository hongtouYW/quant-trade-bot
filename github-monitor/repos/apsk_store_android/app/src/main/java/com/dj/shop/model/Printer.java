package com.dj.shop.model;

public class Printer {
    private String title;
    private String address;
    private int status;

    public Printer(String title, String address, int status) {
        this.title = title;
        this.address = address;
        this.status = status;
    }

    public String getTitle() {
        return title;
    }

    public void setTitle(String title) {
        this.title = title;
    }

    public String getAddress() {
        return address;
    }

    public void setAddress(String address) {
        this.address = address;
    }

    public int getStatus() {
        return status;
    }

    public void setStatus(int status) {
        this.status = status;
    }
}
