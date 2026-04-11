package com.dj.user.model.response;

public class Remain {
    private int days;
    private int hours;
    private int minutes;
    private String general;

    public int getDays() {
        return days;
    }

    public void setDays(int days) {
        this.days = days;
    }

    public int getHours() {
        return hours;
    }

    public void setHours(int hours) {
        this.hours = hours;
    }

    public int getMinutes() {
        return minutes;
    }

    public void setMinutes(int minutes) {
        this.minutes = minutes;
    }

    public double getGeneral() {
        try {
            return Double.parseDouble(general);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setGeneral(String general) {
        this.general = general;
    }
}